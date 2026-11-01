<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

function import_legacy_csv(PDO $pdo, string $path, ?string $sourceName = null, ?array $actor = null, ?int $targetRegionId = null, ?int $targetProvinceId = null): array
{
    if ($actor !== null && !can_import_farm($actor)) throw new RuntimeException('Import réservé à l’administrateur.');
    if (!is_file($path) || !is_readable($path)) throw new RuntimeException('Fichier CSV absent ou illisible.');
    $hash = hash_file('sha256', $path);
    if (!is_string($hash)) throw new RuntimeException('Empreinte du fichier impossible à calculer.');
    $check = $pdo->prepare('SELECT imported_rows FROM legacy_imports WHERE sha256=:hash');
    $check->execute([':hash'=>$hash]);
    if ($check->fetchColumn() !== false) throw new RuntimeException('Ce fichier a déjà été importé (même empreinte SHA-256).');
    $normalize = static function(string $s): string {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($s));
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $ascii === false ? $s : $ascii) ?: '');
    };
    $decodeCell = static function(string $value): string {
        if (preg_match('//u', $value) === 1) return $value;
        $decoded = iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        return $decoded === false ? $value : $decoded;
    };
    $regions = [];
    foreach ($pdo->query('SELECT id,name FROM regions') as $r) $regions[$normalize($r['name'])] = ['id'=>(int)$r['id'],'name'=>$r['name']];
    $provincesByName = []; $provincesByRegionName = [];
    foreach ($pdo->query('SELECT p.id,p.region_id,p.name,r.name AS region_name FROM provinces p JOIN regions r ON r.id=p.region_id') as $p) {
        $item = ['id'=>(int)$p['id'],'region_id'=>(int)$p['region_id'],'region_name'=>$p['region_name'],'name'=>$p['name']];
        $provincesByName[$normalize($p['name'])][] = $item;
        $provincesByRegionName[(int)$p['region_id'] . ':' . $normalize($p['name'])] = $item;
    }
    $legacyCities = ['tanger'=>'Tanger-Assilah','tetouan'=>'Tétouan','alhoceima'=>'Al Hoceïma','chefchaouen'=>'Chefchaouen','larache'=>'Larache','ouezzane'=>'Ouezzane'];
    $insert = $pdo->prepare('INSERT INTO farms (region_id,province_id,authorization_status,agreement_number,authorization_number,owner_name,national_id,farm_name,latitude,longitude,farm_type,first_agreement_date,second_agreement_date,construction_deadline) VALUES (:region,:province,:status,:agreement,:authorization,:owner,:cnie,:name,:lat,:lon,:type,:d1,:d2,:d3)');
    $duplicateCheck = $pdo->prepare('SELECT id FROM farms WHERE region_id=:region AND province_id=:province AND farm_name=:name AND latitude=:lat AND longitude=:lon LIMIT 1');
    $fh = fopen($path, 'rb');
    if (!$fh) throw new RuntimeException('Impossible de lire le CSV.');
    $pdo->beginTransaction();
    try {
        $line = 0; $imported = 0; $demoSkipped = 0; $duplicates = 0; $exportFormat = false;
        while (($row = fgetcsv($fh, 0, ';', '"', '')) !== false) {
            $line++;
            if ($row === [null] || $row === []) continue;
            if (isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
            if (count($row) !== 14) throw new RuntimeException("Ligne {$line}: 14 colonnes attendues, " . count($row) . ' reçues. Utilisez un export CSV à 14 colonnes séparé par des points-virgules.');
            $row = array_map(static fn($v)=>trim($decodeCell((string)$v)), $row);
            if ($line === 1 && $normalize($row[0]) === 'region' && $normalize($row[2]) === 'statut') {
                $exportFormat = true;
                continue;
            }
            if ($exportFormat) {
                [$regionName,$provinceName,$status,$agreement,$authorization,$owner,$cnie,$name,$lat,$lon,$type,$d1,$d2,$d3] = $row;
            } else {
                [$authorization,$status,$agreement,$owner,$cnie,$name,$regionName,$provinceName,$lat,$lon,$type,$d1,$d2,$d3] = $row;
            }
            if (!in_array($status, FARM_STATUSES, true)) throw new RuntimeException("Ligne {$line}: statut d’autorisation inconnu.");
            if (!in_array($type, FARM_TYPES, true)) throw new RuntimeException("Ligne {$line}: type d’unité inconnu.");
            if ($name === '') throw new RuntimeException("Ligne {$line}: nom d’unité vide.");
            $nameKey = $normalize($name);
            if (str_starts_with($nameKey, 'fermedemo') || str_starts_with($nameKey, 'unite') || str_starts_with(strtoupper($cnie), 'DEMO-')) { $demoSkipped++; continue; }
            $region = $regionName !== '' ? ($regions[$normalize($regionName)] ?? null) : null;
            if ($regionName !== '' && !$region) throw new RuntimeException("Ligne {$line}: région inconnue ({$regionName}).");
            $province = null;
            if ($provinceName !== '') {
                if ($region) $province = $provincesByRegionName[$region['id'] . ':' . $normalize($provinceName)] ?? null;
                else { $matches = $provincesByName[$normalize($provinceName)] ?? []; if (count($matches) === 1) $province = $matches[0]; }
            }
            if (!$province && $provinceName === '' && preg_match('/^Unité\s+(.+)/iu', $name, $match)) {
                $cityKey = $normalize($match[1]);
                foreach ($legacyCities as $city=>$provinceNameLegacy) {
                    if (str_starts_with($cityKey, $city)) { $matches=$provincesByName[$normalize($provinceNameLegacy)] ?? []; if (count($matches)===1) $province=$matches[0]; break; }
                }
            }
            if (!$province) throw new RuntimeException("Ligne {$line}: province/préfecture absente ou non reconnue ({$provinceName}).");
            if ($region && $province['region_id'] !== $region['id']) throw new RuntimeException("Ligne {$line}: province et région incompatibles.");
            if ($actor !== null) {
                if (has_region_scope($actor) && (int)($actor['region_id'] ?? 0) !== (int)$province['region_id']) {
                    throw new RuntimeException("Ligne {$line}: le profil régional/divisionnaire ne peut importer que les unités de sa région.");
                }
                if (has_province_scope($actor) && (int)($actor['province_id'] ?? 0) !== (int)$province['id']) {
                    throw new RuntimeException("Ligne {$line}: ce profil ne peut importer que les unités de sa province.");
                }
            }
            if ($targetRegionId !== null && (int)$province['region_id'] !== $targetRegionId) throw new RuntimeException("Ligne {$line}: la ligne ne correspond pas à la région choisie pour l’import.");
            if ($targetProvinceId !== null && (int)$province['id'] !== $targetProvinceId) throw new RuntimeException("Ligne {$line}: la ligne ne correspond pas à la province choisie pour l’import.");
            $lat=str_replace(',', '.', $lat); $lon=str_replace(',', '.', $lon);
            if (!is_numeric($lat)||!is_numeric($lon)||(float)$lat < -90||(float)$lat > 90||(float)$lon < -180||(float)$lon > 180) throw new RuntimeException("Ligne {$line}: latitude/longitude invalide.");
            $dateValue=static function(string $value,int $line):?string { if($value==='')return null; foreach(['!Y-m-d'=>'Y-m-d','!d/m/Y'=>'d/m/Y'] as $format=>$display){$d=DateTime::createFromFormat($format,$value);if($d&&$d->format($display)===$value)return $d->format('Y-m-d');} throw new RuntimeException("Ligne {$line}: date invalide ({$value}); formats acceptés : AAAA-MM-JJ ou JJ/MM/AAAA."); };
            $latValue=(float)$lat; $lonValue=(float)$lon;
            $duplicateCheck->execute([':region'=>$province['region_id'],':province'=>$province['id'],':name'=>$name,':lat'=>$latValue,':lon'=>$lonValue]);
            if ($duplicateCheck->fetchColumn() !== false) { $duplicates++; continue; }
            $insert->execute([':region'=>$province['region_id'],':province'=>$province['id'],':status'=>$status,':agreement'=>$agreement?:null,':authorization'=>$authorization?:null,':owner'=>$owner?:null,':cnie'=>$cnie?:null,':name'=>$name,':lat'=>$latValue,':lon'=>$lonValue,':type'=>$type,':d1'=>$dateValue($d1,$line),':d2'=>$dateValue($d2,$line),':d3'=>$dateValue($d3,$line)]);
            $imported++;
        }
        fclose($fh);
        if ($imported===0 && $duplicates===0) throw new RuntimeException('Aucune ligne réelle à importer (lignes de démonstration filtrées : ' . $demoSkipped . ').');
        $pdo->prepare('INSERT INTO legacy_imports (sha256,source_name,imported_rows) VALUES (:hash,:name,:rows)')->execute([':hash'=>$hash,':name'=>substr(basename($sourceName ?? $path),0,255),':rows'=>$imported]);
        $pdo->commit();
        return ['imported'=>$imported,'demo_skipped'=>$demoSkipped,'duplicates'=>$duplicates];
    } catch (Throwable $ex) {
        if (is_resource($fh)) fclose($fh);
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}
