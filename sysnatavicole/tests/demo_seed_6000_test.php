<?php
declare(strict_types=1);

require_once __DIR__ . '/../bin/demo_seed_helpers.php';

function seed_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$dataPath = $root . '/data/morocco_adm2_boundaries.geojson';
$boundaryData = json_decode((string)file_get_contents($dataPath), true, 512, JSON_THROW_ON_ERROR);
$base = rtrim((string)(getenv('TMPDIR') ?: sys_get_temp_dir()), '/\\') . DIRECTORY_SEPARATOR . 'demo-seed-6000-test';
@mkdir($base, 0777, true);
$dbPath = $base . DIRECTORY_SEPARATOR . 'fixture.sqlite';
$credentialsPath = $base . DIRECTORY_SEPARATOR . 'credentials.csv';
$retryCredentialsPath = $base . DIRECTORY_SEPARATOR . 'retry-credentials.csv';
@unlink($dbPath);
@unlink($credentialsPath);
@unlink($retryCredentialsPath);

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec('CREATE TABLE regions (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE)');
$pdo->exec('CREATE TABLE provinces (id INTEGER PRIMARY KEY, region_id INTEGER NOT NULL, name TEXT NOT NULL, UNIQUE(region_id,name), FOREIGN KEY(region_id) REFERENCES regions(id))');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, full_name TEXT NOT NULL, role TEXT NOT NULL, region_id INTEGER NULL, province_id INTEGER NULL, active INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(region_id) REFERENCES regions(id), FOREIGN KEY(province_id) REFERENCES provinces(id))');
$pdo->exec('CREATE TABLE farms (id INTEGER PRIMARY KEY AUTOINCREMENT, region_id INTEGER NOT NULL, province_id INTEGER NOT NULL, authorization_status TEXT NOT NULL, agreement_number TEXT, authorization_number TEXT, owner_name TEXT, national_id TEXT, farm_name TEXT NOT NULL, latitude REAL NOT NULL, longitude REAL NOT NULL, farm_type TEXT NOT NULL, first_agreement_date TEXT, second_agreement_date TEXT, construction_deadline TEXT, created_by INTEGER NULL, updated_by INTEGER NULL, FOREIGN KEY(region_id) REFERENCES regions(id), FOREIGN KEY(province_id) REFERENCES provinces(id), FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id))');
$regions = [];
$provinces = [];
foreach ($boundaryData['features'] as $feature) {
    $props = $feature['properties'];
    $regions[(int)$props['appRegionId']] = (string)$props['appRegionName'];
    $provinces[] = [(int)$props['appRegionId'], (string)$props['appRegionName'], (string)$props['appProvinceName']];
}
ksort($regions);
$insertRegion = $pdo->prepare('INSERT INTO regions(id,name) VALUES(:id,:name)');
foreach ($regions as $id => $name) $insertRegion->execute([':id' => $id, ':name' => $name]);
$insertProvince = $pdo->prepare('INSERT INTO provinces(id,region_id,name) VALUES(:id,:region_id,:name)');
$provinceId = 0;
foreach ($provinces as [$regionId, $regionName, $name]) $insertProvince->execute([':id' => ++$provinceId, ':region_id' => $regionId, ':name' => $name]);
$pdo->prepare('INSERT INTO users(email,password_hash,full_name,role,region_id,province_id,active) VALUES(?,?,?,?,NULL,NULL,1)')->execute(['initial@example.test', password_hash('initial-test-password', PASSWORD_DEFAULT), 'Compte initial', 'central']);
$pdo = null;

$environment = getenv();
$environment['DB_DSN'] = 'sqlite:' . str_replace('\\', '/', $dbPath);
$command = [PHP_BINARY, $root . '/bin/seed_demo_6000.php', '--confirm-demo', $credentialsPath];
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
if (!is_resource($process)) throw new RuntimeException('Impossible de démarrer le générateur de démonstration.');
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);
seed_assert($status === 0, 'Le générateur a échoué : ' . trim((string)$stderr));
seed_assert(str_contains((string)$stdout, '6 000 fermes fictives'), 'Le générateur confirme le chargement attendu.');
seed_assert(is_file($credentialsPath), 'Le CSV privé des profils est créé.');

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
seed_assert((int)$pdo->query('SELECT COUNT(*) FROM farms')->fetchColumn() === 6000, 'Il y a exactement 6 000 fermes de démonstration.');
$distribution = $pdo->query('SELECT province_id,COUNT(*) AS total FROM farms GROUP BY province_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$distributionValues = array_map('intval', array_values($distribution));
seed_assert(count($distributionValues) === 75 && min($distributionValues) > 0 && max($distributionValues) > min($distributionValues) && array_sum($distributionValues) === 6000, 'Les 6 000 fermes sont distribuées aléatoirement et toutes les provinces sont couvertes.');
$profileCounts = $pdo->query("SELECT role,COUNT(*) FROM users WHERE email LIKE 'demo.%@example.test' GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
seed_assert((int)($profileCounts['central'] ?? 0) === 1 && (int)($profileCounts['regional'] ?? 0) === 12 && (int)($profileCounts['provincial'] ?? 0) === 75, 'Les trois rôles DEMO ont leurs profils affectés.');
$badAssignments = (int)$pdo->query('SELECT COUNT(*) FROM farms f JOIN provinces p ON p.id=f.province_id WHERE f.region_id<>p.region_id')->fetchColumn();
seed_assert($badAssignments === 0, 'Chaque ferme est associée à la région de sa province.');
$regionCoverage = (int)$pdo->query('SELECT COUNT(DISTINCT region_id) FROM farms')->fetchColumn();
seed_assert($regionCoverage === 12, 'Les fermes couvrent les 12 régions.');

$geoByProvince = [];
foreach ($boundaryData['features'] as $feature) {
    $props = $feature['properties'];
    $key = $props['appRegionName'] . "\0" . $props['appProvinceName'];
    $geoByProvince[$key] = demo_prepare_geometry($feature['geometry']);
}
$provinceRows = $pdo->query('SELECT p.id,p.name AS province_name,r.name AS region_name FROM provinces p JOIN regions r ON r.id=p.region_id')->fetchAll(PDO::FETCH_UNIQUE);
foreach ($pdo->query('SELECT province_id,latitude,longitude FROM farms') as $farm) {
    $province = $provinceRows[(int)$farm['province_id']];
    $components = $geoByProvince[$province['region_name'] . "\0" . $province['province_name']];
    seed_assert(demo_geometry_contains((float)$farm['longitude'], (float)$farm['latitude'], $components), 'Coordonnée hors de la province associée : ' . $province['province_name']);
}

$credentialHandle = fopen($credentialsPath, 'rb');
$credentialHeader = fgetcsv($credentialHandle, 0, ';', '"', '');
seed_assert($credentialHeader === ['email', 'mot_de_passe', 'profil', 'region', 'province'], 'L’en-tête du CSV des identifiants est valide.');
$credentialEmails = [];
$credentialPasswords = [];
$hashQuery = $pdo->prepare('SELECT password_hash FROM users WHERE email=:email');
while (($row = fgetcsv($credentialHandle, 0, ';', '"', '')) !== false) {
    if ($row === [null] || $row === []) continue;
    [$email, $password, $role] = $row;
    seed_assert(str_ends_with($email, '@example.test'), 'Les comptes de démonstration utilisent le domaine réservé .test.');
    seed_assert(!isset($credentialEmails[$email]) && !isset($credentialPasswords[$password]), 'Les e-mails et mots de passe du CSV sont uniques.');
    $credentialEmails[$email] = true;
    $credentialPasswords[$password] = true;
    $hashQuery->execute([':email' => $email]);
    $hash = $hashQuery->fetchColumn();
    seed_assert(is_string($hash) && password_verify($password, $hash), 'Le mot de passe du CSV correspond à son hachage PHP.');
}
fclose($credentialHandle);
seed_assert(count($credentialEmails) === 88, 'Le CSV privé contient exactement 88 identifiants générés.');

$retryCommand = [PHP_BINARY, $root . '/bin/seed_demo_data.php', '--confirm-demo', $retryCredentialsPath];
$retry = proc_open($retryCommand, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $retryPipes, $root, $environment);
if (!is_resource($retry)) throw new RuntimeException('Impossible de vérifier le garde anti-doublon.');
fclose($retryPipes[0]);
$retryOut = stream_get_contents($retryPipes[1]);
$retryErr = stream_get_contents($retryPipes[2]);
fclose($retryPipes[1]);
fclose($retryPipes[2]);
$retryStatus = proc_close($retry);
seed_assert($retryStatus !== 0 && !is_file($retryCredentialsPath), 'Une seconde exécution est bloquée sans générer de credentials.');
seed_assert((int)$pdo->query('SELECT COUNT(*) FROM farms')->fetchColumn() === 6000, 'Le garde anti-doublon n’ajoute pas de nouvelles fermes.');

$pdo = null;
@unlink($dbPath);
@unlink($credentialsPath);
@unlink($retryCredentialsPath);
@rmdir($base);
echo "OK: 6 000 fermes; répartition aléatoire dans les 75 provinces; 12 régions; points dans leurs polygones; 88 profils .test et mots de passe vérifiés; anti-doublon actif.\n";
