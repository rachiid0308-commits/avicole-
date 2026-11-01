<?php
declare(strict_types=1);
require_once __DIR__ . '/../htdocs/controls_domain.php';

function db_check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE control_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, region_id INTEGER NOT NULL, province_id INTEGER NOT NULL, farm_id INTEGER, inspection_type TEXT NOT NULL, inspection_date TEXT NOT NULL, commission_label TEXT, unit_name TEXT NOT NULL, authorization_number TEXT, owner_name TEXT, metadata_json TEXT NOT NULL, conclusion TEXT NOT NULL, created_by INTEGER)');
$pdo->exec('CREATE TABLE control_report_answers (id INTEGER PRIMARY KEY AUTOINCREMENT, report_id INTEGER NOT NULL, item_key TEXT NOT NULL, answer TEXT, observation TEXT)');
$pdo->exec('CREATE TABLE control_report_controllers (id INTEGER PRIMARY KEY AUTOINCREMENT, report_id INTEGER NOT NULL, sort_order INTEGER NOT NULL, full_name TEXT NOT NULL, function_title TEXT, signature_text TEXT)');
$pdo->exec('CREATE TABLE control_report_photos (report_id INTEGER NOT NULL, view_key TEXT NOT NULL, mime_type TEXT NOT NULL, image_data BLOB NOT NULL, PRIMARY KEY(report_id, view_key))');

$base = [
    'region_id' => 1, 'province_id' => 2, 'farm_id' => 8, 'type' => 'chair',
    'inspection_date' => '2026-10-28', 'commission_label' => 'Commission provinciale',
    'unit_name' => 'Unité Test', 'authorization_number' => 'AUT-008', 'owner_name' => 'Propriétaire Test',
    'metadata' => ['responsible' => 'Responsable', 'address' => 'Adresse'],
    'conclusion' => 'Correction requise', 'created_by' => 4,
];
try {
    control_create($pdo, $base, ['fence' => ['status' => 'maybe']], [['name' => 'Testeur']]);
    db_check(false, 'invalid compliance values are rejected');
} catch (InvalidArgumentException) {
    db_check((int)$pdo->query('SELECT COUNT(*) FROM control_reports')->fetchColumn() === 0, 'invalid answer stores no partial report');
}

try {
    control_create($pdo, $base, [], [['name' => str_repeat('N', 191)]]);
    db_check(false, 'overlength controller names are rejected');
} catch (InvalidArgumentException) {
    db_check((int)$pdo->query('SELECT COUNT(*) FROM control_reports')->fetchColumn() === 0, 'invalid controller creates no report');
}

$id = control_create($pdo, $base, [
    'fence' => ['status' => 'oui', 'observation' => 'À réparer rapidement'],
    'not_a_real_criterion' => ['status' => 'non', 'observation' => 'ignored'],
], [['name' => 'Amina El Fassi', 'function' => 'Vétérinaire', 'signature_text' => 'A. El Fassi']]);
db_check($id === 1, 'report is saved and receives an id');
db_check((int)$pdo->query('SELECT COUNT(*) FROM control_report_answers')->fetchColumn() === 1, 'only checklist criteria are persisted');
$answer = $pdo->query('SELECT answer, observation FROM control_report_answers')->fetch(PDO::FETCH_ASSOC);
db_check($answer['answer'] === 'oui' && $answer['observation'] === 'À réparer rapidement', 'yes/no response and observation are stored');
$controller = $pdo->query('SELECT full_name, signature_text FROM control_report_controllers')->fetch(PDO::FETCH_ASSOC);
db_check($controller['full_name'] === 'Amina El Fassi' && $controller['signature_text'] === 'A. El Fassi', 'controller name and signature are stored');

$transport = $base;
$transport['farm_id'] = null;
$transport['type'] = 'transport';
$transport['unit_name'] = '';
$transport['authorization_number'] = '';
$transport['metadata'] = ['registration' => '12345-A-01', 'products' => 'live_poultry'];
$transport['owner_name'] = 'Transport Test';
$photoBytes = 'PNG-PHOTO-BINARY';
$transportId = control_create($pdo, $transport, [], [['name' => 'Contrôleur']], ['front' => ['mime_type' => 'image/png', 'image_data' => $photoBytes]]);
$photo = $pdo->query('SELECT view_key,mime_type,image_data FROM control_report_photos WHERE report_id=' . $transportId)->fetch(PDO::FETCH_ASSOC);
db_check($photo['view_key'] === 'front' && $photo['mime_type'] === 'image/png' && $photo['image_data'] === $photoBytes, 'transport vehicle photo bytes and view are stored in the database');
$transport['metadata']['registration'] = '12345-B-02';
control_update($pdo, $transportId, $transport, [], [['name' => 'Contrôleur']], ['front' => ['mime_type' => 'image/jpeg', 'image_data' => 'REPLACED-PHOTO'], 'left' => ['mime_type' => 'image/webp', 'image_data' => 'LEFT-PHOTO']]);
$photos = $pdo->query('SELECT view_key,mime_type,image_data FROM control_report_photos WHERE report_id=' . $transportId)->fetchAll(PDO::FETCH_ASSOC);
$photosByView = [];
foreach ($photos as $savedPhoto) $photosByView[$savedPhoto['view_key']] = $savedPhoto;
db_check(count($photosByView) === 2 && $photosByView['front']['image_data'] === 'REPLACED-PHOTO' && $photosByView['left']['image_data'] === 'LEFT-PHOTO', 'editing replaces only uploaded photo views and retains all saved image data');
echo "OK: contrôle SQL SQLite and transport photo persistence tests passed\n";
