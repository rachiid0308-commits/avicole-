<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--confirm-demo') {
    fwrite(STDERR, "Exécution locale uniquement : php bin/seed_demo_data.php --confirm-demo [fichier-privé-comptes.csv]\nLe chargement recommandé ajoute 6 000 fermes DEMO dans toutes les provinces.\n");
    exit(2);
}
require __DIR__ . '/seed_demo_6000.php';
exit;

$root = dirname(__DIR__);
$appPath = is_file($root . '/app.php') ? $root . '/app.php' : $root . '/htdocs/app.php';
if (!is_file($appPath)) { fwrite(STDERR, "app.php introuvable.\n"); exit(2); }
require_once $appPath;

try {
    $pdo = db();
    $existingDemoRows = (int)$pdo->query("SELECT COUNT(*) FROM farms WHERE farm_name LIKE 'DEMO TEST %'")->fetchColumn();
    if ($existingDemoRows > 0) throw new RuntimeException("{$existingDemoRows} unité(s) DEMO TEST existent déjà; aucune donnée n’a été modifiée.");
    $demoEmails = ['demo.central@example.test','demo.regional@example.test','demo.provincial@example.test'];
    $emailProbe = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=:email');
    foreach ($demoEmails as $email) {
        $emailProbe->execute([':email'=>$email]);
        if ((int)$emailProbe->fetchColumn() > 0) throw new RuntimeException("Le compte {$email} existe déjà; aucune donnée n’a été modifiée.");
    }
    $regions = $pdo->query('SELECT id,name FROM regions ORDER BY id')->fetchAll();
    $provinces = $pdo->query('SELECT p.id,p.region_id,p.name AS province_name,r.name AS region_name FROM provinces p JOIN regions r ON r.id=p.region_id ORDER BY p.id')->fetchAll();
    if (count($regions) !== 12 || count($provinces) !== 75) throw new RuntimeException('La base doit contenir les 12 régions et les 75 provinces avant le chargement de démonstration.');
    $roleCounts = $pdo->query('SELECT role,COUNT(*) AS total FROM users GROUP BY role')->fetchAll();
    $counts = ['central'=>0,'regional'=>0,'provincial'=>0];
    foreach ($roleCounts as $row) $counts[$row['role']] = (int)$row['total'];
    foreach (['central'=>4,'regional'=>12,'provincial'=>75] as $role=>$limit) {
        if ($counts[$role] >= $limit) throw new RuntimeException("Plafond de comptes {$role} atteint; le compte de test ne peut pas être créé.");
    }
    $regional = $pdo->query("SELECT r.id FROM regions r WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.role='regional' AND u.region_id=r.id) ORDER BY r.id LIMIT 1")->fetchColumn();
    $province = $pdo->query("SELECT p.id,p.region_id FROM provinces p WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.role='provincial' AND u.province_id=p.id) ORDER BY p.id LIMIT 1")->fetch();
    if (!$regional || !$province) throw new RuntimeException('Aucune affectation libre pour créer les comptes régionaux et provinciaux de test.');

    $pdo->beginTransaction();
    $insertUser = $pdo->prepare('INSERT INTO users (email,password_hash,full_name,role,region_id,province_id,active) VALUES (:email,:password_hash,:full_name,:role,:region_id,:province_id,1)');
    $accounts = [];
    $accountSpecs = [
        ['email'=>$demoEmails[0],'name'=>'Compte DEMO — Directeur central','role'=>ROLE_CENTRAL,'region'=>null,'province'=>null],
        ['email'=>$demoEmails[1],'name'=>'Compte DEMO — Directeur régional','role'=>ROLE_REGIONAL,'region'=>(int)$regional,'province'=>null],
        ['email'=>$demoEmails[2],'name'=>'Compte DEMO — Chef de service provincial','role'=>ROLE_PROVINCIAL,'region'=>(int)$province['region_id'],'province'=>(int)$province['id']],
    ];
    foreach ($accountSpecs as $spec) {
        $password = bin2hex(random_bytes(10));
        $insertUser->execute([
            ':email'=>$spec['email'], ':password_hash'=>password_hash($password,PASSWORD_DEFAULT),
            ':full_name'=>$spec['name'], ':role'=>$spec['role'],
            ':region_id'=>$spec['region'], ':province_id'=>$spec['province'],
        ]);
        $accounts[] = ['email'=>$spec['email'],'password'=>$password,'role'=>$spec['role']];
        if ($spec['role'] === ROLE_CENTRAL) $centralUserId = (int)$pdo->lastInsertId();
    }

    $centers = [
        1=>[35.0,-5.4], 2=>[34.7,-2.3], 3=>[33.8,-4.8], 4=>[34.1,-6.4],
        5=>[32.3,-6.2], 6=>[33.2,-7.5], 7=>[31.6,-8.1], 8=>[31.0,-5.8],
        9=>[30.4,-9.5], 10=>[28.0,-10.1], 11=>[26.1,-12.8], 12=>[23.7,-15.9],
    ];
    $types = FARM_TYPES;
    $statuses = FARM_STATUSES;
    $insertFarm = $pdo->prepare('INSERT INTO farms (region_id,province_id,authorization_status,agreement_number,authorization_number,owner_name,national_id,farm_name,latitude,longitude,farm_type,first_agreement_date,second_agreement_date,construction_deadline,created_by,updated_by) VALUES (:region_id,:province_id,:authorization_status,:agreement_number,:authorization_number,:owner_name,:national_id,:farm_name,:latitude,:longitude,:farm_type,:first_agreement_date,:second_agreement_date,:construction_deadline,:created_by,:updated_by)');
    $startDate = new DateTimeImmutable('2024-01-01');
    for ($i=1; $i<=200; $i++) {
        $geo = $provinces[($i-1) % count($provinces)];
        $first = $startDate->modify('+' . (($i*11) % 800) . ' days');
        $second = ($i % 8 === 0) ? null : add_calendar_months($first, $i % 10 === 0 ? 2 : 1);
        $construction = $second ? add_calendar_months($second, $i % 9 === 0 ? 15 : 12) : null;
        [$centerLat,$centerLon] = $centers[(int)$geo['region_id']];
        $lat = $centerLat + (($i % 7)-3)*0.045;
        $lon = $centerLon + (($i % 9)-4)*0.055;
        $name = sprintf('DEMO TEST %03d',$i);
        $number = sprintf('%03d',$i);
        $insertFarm->execute([
            ':region_id'=>(int)$geo['region_id'], ':province_id'=>(int)$geo['id'],
            ':authorization_status'=>$statuses[($i-1)%count($statuses)],
            ':agreement_number'=>'DEMO-ACC-'.$number, ':authorization_number'=>'DEMO-AUT-'.$number,
            ':owner_name'=>'Entreprise fictive '.$number, ':national_id'=>'DEMO-TEST-'.$number,
            ':farm_name'=>$name, ':latitude'=>number_format($lat,7,'.',''), ':longitude'=>number_format($lon,7,'.',''),
            ':farm_type'=>$types[($i-1)%count($types)],
            ':first_agreement_date'=>$first->format('Y-m-d'), ':second_agreement_date'=>$second?->format('Y-m-d'),
            ':construction_deadline'=>$construction?->format('Y-m-d'), ':created_by'=>$centralUserId, ':updated_by'=>$centralUserId,
        ]);
    }
    $pdo->commit();
    echo "Chargement terminé : 200 unités fictives dans 75 provinces, réparties sur 12 régions; comptes de test créés :\n";
    foreach ($accounts as $account) echo strtoupper($account['role'])."\t{$account['email']}\t{$account['password']}\n";
    echo "Coordonnées approximatives. Mots de passe temporaires : conservez-les uniquement pour le test et supprimez les comptes après.\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Chargement annulé : ' . $exception->getMessage() . "\n");
    exit(1);
}
