<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--confirm-demo' || $argc > 3) {
    fwrite(STDERR, "Usage : php bin/seed_demo_6000.php --confirm-demo [fichier-privé-comptes.csv]\nAjoute 6 000 fermes fictives réparties au hasard dans les 75 provinces et leurs régions, plus les profils DEMO manquants; ne supprime aucune donnée. Ne pas exécuter en production.\n");
    exit(2);
}
$appPath = is_file($root . '/htdocs/app.php') ? $root . '/htdocs/app.php' : $root . '/app.php';
$helperPath = $root . '/bin/demo_seed_helpers.php';
$boundaryPath = $root . '/data/morocco_adm2_boundaries.geojson';
if (!is_file($appPath) || !is_file($helperPath) || !is_file($boundaryPath)) {
    fwrite(STDERR, "Fichier requis introuvable : app.php, bin/demo_seed_helpers.php ou data/morocco_adm2_boundaries.geojson.\n");
    exit(2);
}
require_once $appPath;
require_once $helperPath;

$credentialPath = $argv[2] ?? ($root . '/bin/demo-credentials-' . gmdate('Ymd-His') . '.csv');
if (!str_starts_with($credentialPath, '/') && !str_starts_with($credentialPath, '\\') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialPath)) {
    $credentialPath = $root . DIRECTORY_SEPARATOR . $credentialPath;
}
$credentialDirectory = realpath(dirname($credentialPath));
$webRoot = realpath($root . '/htdocs');
if (!$credentialDirectory || !is_dir($credentialDirectory) || !is_writable($credentialDirectory)) {
    fwrite(STDERR, "Le dossier de sortie du CSV d'identifiants est absent ou non inscriptible.\n");
    exit(2);
}
if ($webRoot && str_starts_with(strtolower($credentialDirectory . DIRECTORY_SEPARATOR), strtolower($webRoot . DIRECTORY_SEPARATOR))) {
    fwrite(STDERR, "Par sécurité, le CSV contenant les mots de passe doit rester hors du dossier web htdocs.\n");
    exit(2);
}
$credentialPath = $credentialDirectory . DIRECTORY_SEPARATOR . basename($credentialPath);
if (file_exists($credentialPath)) {
    fwrite(STDERR, "Le fichier d'identifiants existe déjà; choisissez un autre chemin : {$credentialPath}\n");
    exit(2);
}

function write_demo_credentials(string $path, array $accounts): void
{
    if (!$accounts) return;
    $temporaryPath = $path . '.tmp-' . bin2hex(random_bytes(6));
    $handle = fopen($temporaryPath, 'xb');
    if (!$handle) throw new RuntimeException('Impossible de créer le CSV privé des identifiants.');
    try {
        if (fputcsv($handle, ['email', 'mot_de_passe', 'profil', 'region', 'province'], ';', '"', '') === false) {
            throw new RuntimeException('Écriture du CSV des identifiants impossible.');
        }
        foreach ($accounts as $account) {
            if (fputcsv($handle, [$account['email'], $account['password'], $account['role'], $account['region_name'], $account['province_name']], ';', '"', '') === false) {
                throw new RuntimeException('Écriture du CSV des identifiants impossible.');
            }
        }
        if (!fflush($handle)) throw new RuntimeException('Écriture du CSV des identifiants incomplète.');
    } catch (Throwable $exception) {
        fclose($handle);
        @unlink($temporaryPath);
        throw $exception;
    }
    fclose($handle);
    @chmod($temporaryPath, 0600);
    if (!rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Impossible de finaliser le CSV privé des identifiants.');
    }
    @chmod($path, 0600);
}

$credentialsCreated = false;
$pdo = null;
try {
    $pdo = db();
    $existingDemoRows = (int)$pdo->query("SELECT COUNT(*) FROM farms WHERE farm_name LIKE 'DEMO TEST %'")->fetchColumn();
    if ($existingDemoRows > 0) throw new RuntimeException("{$existingDemoRows} ferme(s) DEMO TEST existent déjà; aucune donnée n’a été modifiée.");

    $regions = $pdo->query('SELECT id,name FROM regions ORDER BY id')->fetchAll();
    $provinces = $pdo->query('SELECT p.id,p.region_id,p.name AS province_name,r.name AS region_name FROM provinces p JOIN regions r ON r.id=p.region_id ORDER BY p.id')->fetchAll();
    if (count($regions) !== 12 || count($provinces) !== 75) throw new RuntimeException('La base doit contenir les 12 régions et les 75 provinces avant le chargement de démonstration.');

    $boundaryJson = file_get_contents($boundaryPath);
    if (!is_string($boundaryJson) || $boundaryJson === '') throw new RuntimeException('Les limites administratives de démonstration sont illisibles.');
    $boundaryData = json_decode($boundaryJson, true, 512, JSON_THROW_ON_ERROR);
    if (($boundaryData['type'] ?? '') !== 'FeatureCollection' || count($boundaryData['features'] ?? []) !== 75) {
        throw new RuntimeException('Le fichier des limites doit contenir exactement 75 provinces/préfectures.');
    }
    $boundaries = [];
    foreach ($boundaryData['features'] as $feature) {
        $properties = $feature['properties'] ?? [];
        $regionName = (string)($properties['appRegionName'] ?? '');
        $provinceName = (string)($properties['appProvinceName'] ?? '');
        $key = $regionName . "\0" . $provinceName;
        if ($regionName === '' || $provinceName === '' || isset($boundaries[$key])) throw new RuntimeException('Le fichier des limites contient une affectation vide ou répétée.');
        $boundaries[$key] = demo_prepare_geometry($feature['geometry'] ?? []);
    }
    foreach ($provinces as &$province) {
        $key = (string)$province['region_name'] . "\0" . (string)$province['province_name'];
        if (!isset($boundaries[$key])) throw new RuntimeException('Limite géographique introuvable pour ' . $province['province_name'] . ' — ' . $province['region_name'] . '.');
        $province['boundary_components'] = $boundaries[$key];
        demo_sample_point($province['boundary_components']);
    }
    unset($province);

    $roleCounts = $pdo->query('SELECT role,COUNT(*) AS total FROM users GROUP BY role')->fetchAll();
    $counts = ['central' => 0, 'regional' => 0, 'provincial' => 0];
    foreach ($roleCounts as $row) {
        if (array_key_exists($row['role'], $counts)) $counts[$row['role']] = (int)$row['total'];
    }
    $limits = ['central' => 4, 'regional' => 12, 'provincial' => 75];
    foreach ($counts as $role => $count) {
        if ($count > $limits[$role]) throw new RuntimeException("Le nombre de comptes {$role} dépasse le plafond configuré.");
    }
    $occupiedRegions = [];
    foreach ($pdo->query("SELECT region_id FROM users WHERE role='regional'")->fetchAll() as $row) $occupiedRegions[(int)$row['region_id']] = true;
    $occupiedProvinces = [];
    foreach ($pdo->query("SELECT province_id FROM users WHERE role='provincial'")->fetchAll() as $row) $occupiedProvinces[(int)$row['province_id']] = true;

    $accountSpecs = [];
    $emailProbe = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=:email');
    $addAccount = static function (array $spec) use (&$accountSpecs, $emailProbe): void {
        $emailProbe->execute([':email' => $spec['email']]);
        if ((int)$emailProbe->fetchColumn() > 0) throw new RuntimeException('Le compte de démonstration ' . $spec['email'] . ' existe déjà; aucune donnée n’a été modifiée.');
        $accountSpecs[] = $spec;
    };
    if ($counts['central'] < $limits['central']) {
        $addAccount(['email' => 'demo.central@example.test', 'name' => 'DEMO — Direction centrale', 'role' => ROLE_CENTRAL, 'region_id' => null, 'province_id' => null, 'region_name' => '', 'province_name' => '']);
        $counts['central']++;
    }
    foreach ($regions as $region) {
        $regionId = (int)$region['id'];
        if (isset($occupiedRegions[$regionId])) continue;
        if ($counts['regional'] >= $limits['regional']) throw new RuntimeException('Le plafond de 12 profils régionaux est atteint avant couverture des régions.');
        $number = sprintf('%02d', $regionId);
        $addAccount(['email' => "demo.regional.r{$number}@example.test", 'name' => 'DEMO — Direction régionale — ' . $region['name'], 'role' => ROLE_REGIONAL, 'region_id' => $regionId, 'province_id' => null, 'region_name' => $region['name'], 'province_name' => '']);
        $counts['regional']++;
    }
    foreach ($provinces as $province) {
        $provinceId = (int)$province['id'];
        if (isset($occupiedProvinces[$provinceId])) continue;
        if ($counts['provincial'] >= $limits['provincial']) throw new RuntimeException('Le plafond de 75 profils provinciaux est atteint avant couverture des provinces.');
        $number = sprintf('%03d', $provinceId);
        $addAccount(['email' => "demo.provincial.p{$number}@example.test", 'name' => 'DEMO — Service provincial — ' . $province['province_name'], 'role' => ROLE_PROVINCIAL, 'region_id' => (int)$province['region_id'], 'province_id' => $provinceId, 'region_name' => $province['region_name'], 'province_name' => $province['province_name']]);
        $counts['provincial']++;
    }

    $today = new DateTimeImmutable('today');
    $startDate = new DateTimeImmutable('2020-01-01');
    $maxFirstAgreementDays = max(0, (int)$startDate->diff($today)->days - 24);
    $statuses = FARM_STATUSES;
    $types = FARM_TYPES;
    $credentialRows = [];
    $demoCentralId = null;
    $regionalDemoIds = [];
    $provincialDemoIds = [];
    $profilesCreated = ['central' => 0, 'regional' => 0, 'provincial' => 0];
    $credentialsCreated = false;

    $pdo->beginTransaction();
    $insertUser = $pdo->prepare('INSERT INTO users (email,password_hash,full_name,role,region_id,province_id,active) VALUES (:email,:password_hash,:full_name,:role,:region_id,:province_id,1)');
    foreach ($accountSpecs as $spec) {
        $password = bin2hex(random_bytes(16));
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($passwordHash)) throw new RuntimeException('Impossible de sécuriser un mot de passe de démonstration.');
        $insertUser->execute([':email' => $spec['email'], ':password_hash' => $passwordHash, ':full_name' => $spec['name'], ':role' => $spec['role'], ':region_id' => $spec['region_id'], ':province_id' => $spec['province_id']]);
        $userId = (int)$pdo->lastInsertId();
        if ($spec['role'] === ROLE_CENTRAL) $demoCentralId = $userId;
        elseif ($spec['role'] === ROLE_REGIONAL) $regionalDemoIds[(int)$spec['region_id']] = $userId;
        elseif ($spec['role'] === ROLE_PROVINCIAL) $provincialDemoIds[(int)$spec['province_id']] = $userId;
        $profilesCreated[$spec['role']]++;
        $credentialRows[] = ['email' => $spec['email'], 'password' => $password, 'role' => $spec['role'], 'region_name' => $spec['region_name'], 'province_name' => $spec['province_name']];
    }

    $insertFarm = $pdo->prepare('INSERT INTO farms (region_id,province_id,authorization_status,agreement_number,authorization_number,owner_name,national_id,farm_name,latitude,longitude,farm_type,first_agreement_date,second_agreement_date,construction_deadline,created_by,updated_by) VALUES (:region_id,:province_id,:authorization_status,:agreement_number,:authorization_number,:owner_name,:national_id,:farm_name,:latitude,:longitude,:farm_type,:first_agreement_date,:second_agreement_date,:construction_deadline,:created_by,:updated_by)');
    $farmTotal = 6000;
    $provinceFarmCounts = demo_distribute_farm_counts(array_map(static fn(array $province): int => (int)$province['id'], $provinces), $farmTotal);
    $provinceSlots = [];
    foreach ($provinces as $province) {
        $provinceId = (int)$province['id'];
        for ($i = 0; $i < $provinceFarmCounts[$provinceId]; $i++) $provinceSlots[] = $province;
    }
    shuffle($provinceSlots);
    foreach ($provinceSlots as $index => $province) {
        $serial = $index + 1;
        [$latitude, $longitude] = demo_sample_point($province['boundary_components']);
        $first = $startDate->modify('+' . random_int(0, $maxFirstAgreementDays) . ' days');
        $second = random_int(1, 6) === 1 ? null : add_calendar_months($first, random_int(1, 2));
        $construction = $second ? add_calendar_months($second, random_int(12, 15)) : null;
        $regionId = (int)$province['region_id'];
        $provinceId = (int)$province['id'];
        $creatorPool = array_values(array_filter([$demoCentralId, $regionalDemoIds[$regionId] ?? null, $provincialDemoIds[$provinceId] ?? null], static fn(?int $id): bool => $id !== null));
        $creatorId = $creatorPool ? $creatorPool[random_int(0, count($creatorPool) - 1)] : null;
        $number = sprintf('%06d', $serial);
        $insertFarm->execute([
            ':region_id' => $regionId, ':province_id' => $provinceId,
            ':authorization_status' => $statuses[random_int(0, count($statuses) - 1)],
            ':agreement_number' => 'DEMO-ACC-' . $number, ':authorization_number' => 'DEMO-AUT-' . $number,
            ':owner_name' => 'Entreprise fictive DEMO ' . $number, ':national_id' => 'DEMO-TEST-' . $number,
            ':farm_name' => 'DEMO TEST ' . $number,
            ':latitude' => number_format($latitude, 7, '.', ''), ':longitude' => number_format($longitude, 7, '.', ''),
            ':farm_type' => $types[random_int(0, count($types) - 1)],
            ':first_agreement_date' => $first->format('Y-m-d'), ':second_agreement_date' => $second?->format('Y-m-d'),
            ':construction_deadline' => $construction?->format('Y-m-d'), ':created_by' => $creatorId, ':updated_by' => $creatorId,
        ]);
    }

    write_demo_credentials($credentialPath, $credentialRows);
    $credentialsCreated = $credentialRows !== [];
    $pdo->commit();
    $minProvinceFarms = min($provinceFarmCounts);
    $maxProvinceFarms = max($provinceFarmCounts);
    echo "Chargement terminé : 6 000 fermes fictives réparties aléatoirement dans les 75 provinces/préfectures et les 12 régions (minimum {$minProvinceFarms}, maximum {$maxProvinceFarms} par province).\n";
    echo 'Profils DEMO créés : central=' . $profilesCreated['central'] . ', régional=' . $profilesCreated['regional'] . ', provincial=' . $profilesCreated['provincial'] . ".\n";
    echo $credentialRows ? "CSV privé des identifiants : {$credentialPath}\n" : "Aucun profil DEMO supplémentaire requis; les comptes déjà présents couvrent les affectations.\n";
    echo "Coordonnées et fermes entièrement fictives; les points sont échantillonnés à l’intérieur des polygones administratifs DEMO.\n";
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($credentialsCreated) @unlink($credentialPath);
    fwrite(STDERR, 'Chargement annulé : ' . $exception->getMessage() . "\n");
    exit(1);
}
