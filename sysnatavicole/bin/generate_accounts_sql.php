<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || $argc !== 3 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php bin/generate_accounts_sql.php comptes.csv comptes.sql\nCSV: email;nom;role;region_id;province_id;mot_de_passe\n"); exit(2);
}
$input = fopen($argv[1], 'rb');
if (!$input) { fwrite(STDERR, "CSV illisible.\n"); exit(1); }
$header = fgetcsv($input, 0, ';', '"', '');
if (isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
$expected = ['email','nom','role','region_id','province_id','mot_de_passe'];
if ($header !== $expected) { fwrite(STDERR, "En-tête attendu : " . implode(';', $expected) . "\n"); fclose($input); exit(2); }
$regionOfProvince = [];
foreach ([1=>8,2=>8,3=>9,4=>7,5=>5,6=>9,7=>8,8=>5,9=>6,10=>4,11=>4,12=>2] as $region=>$count) {
    $start = count($regionOfProvince) + 1;
    for ($province=$start; $province<$start+$count; $province++) $regionOfProvince[$province] = $region;
}
$rows=[]; $emails=[]; $regional=[]; $provincial=[]; $counts=['central'=>0,'regional'=>0,'provincial'=>0]; $line=1;
while (($row=fgetcsv($input,0,';','"','')) !== false) {
    $line++;
    if ($row === [null] || $row === []) continue;
    if (count($row)!==6) { fwrite(STDERR,"Ligne {$line}: 6 colonnes attendues.\n"); fclose($input); exit(1); }
    [$email,$name,$role,$regionText,$provinceText,$password]=array_map(static fn($v)=>trim((string)$v),$row);
    $email=strtolower($email); $regionId=$regionText===''?null:filter_var($regionText,FILTER_VALIDATE_INT); $provinceId=$provinceText===''?null:filter_var($provinceText,FILTER_VALIDATE_INT);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || $name==='' || !in_array($role,['central','regional','provincial'],true) || strlen($password)<12 || isset($emails[$email])) {
        fwrite(STDERR,"Ligne {$line}: e-mail, nom, rôle, mot de passe (12 caractères minimum) invalide(s) ou e-mail répété.\n"); fclose($input); exit(1);
    }
    if ($role==='central') {
        if ($regionId!==null || $provinceId!==null) { fwrite(STDERR,"Ligne {$line}: compte central sans région ni province.\n"); fclose($input); exit(1); }
    } elseif ($role==='regional') {
        if (!$regionId || $regionId<1 || $regionId>12 || $provinceId!==null || isset($regional[$regionId])) { fwrite(STDERR,"Ligne {$line}: affectation régionale invalide ou répétée.\n"); fclose($input); exit(1); }
        $regional[$regionId]=true;
    } else {
        if (!$provinceId || !isset($regionOfProvince[$provinceId]) || !$regionId || $regionOfProvince[$provinceId] !== $regionId || isset($provincial[$provinceId])) { fwrite(STDERR,"Ligne {$line}: province/région invalide ou province affectée plusieurs fois.\n"); fclose($input); exit(1); }
        $provincial[$provinceId]=true;
    }
    $counts[$role]++; $emails[$email]=true;
    if ($counts[$role] > ['central'=>4,'regional'=>12,'provincial'=>75][$role]) { fwrite(STDERR,"Ligne {$line}: plafond de comptes atteint pour le rôle {$role}.\n"); fclose($input); exit(1); }
    $rows[]=['email'=>$email,'name'=>$name,'role'=>$role,'region'=>$role==='central'?null:(int)$regionId,'province'=>$role==='provincial'?(int)$provinceId:null,'hash'=>password_hash($password,PASSWORD_DEFAULT)];
}
fclose($input);
if (!$rows) { fwrite(STDERR,"Aucun compte à générer.\n"); exit(1); }
$quote=static fn(?string $s):string => $s===null?'NULL':"'".str_replace("'","''",$s)."'";
$sql="-- Hashes générés localement. Ne pas publier ce fichier.\nSTART TRANSACTION;\n";
foreach ($rows as $r) $sql .= 'INSERT INTO users (email,password_hash,full_name,role,region_id,province_id,active) VALUES (' . implode(',',[$quote($r['email']),$quote($r['hash']),$quote($r['name']),$quote($r['role']),$r['region']===null?'NULL':(string)$r['region'],$r['province']===null?'NULL':(string)$r['province'],'1']) . ");\n";
$sql .= "COMMIT;\n";
if (file_put_contents($argv[2],$sql)===false) { fwrite(STDERR,"Impossible d’écrire le SQL.\n"); exit(1); }
echo "SQL prêt : " . count($rows) . " compte(s) (" . implode(', ',array_map(static fn($k,$v)=>"{$k}={$v}",array_keys($counts),array_values($counts))) . "). Supprimez le CSV contenant les mots de passe après import.\n";
