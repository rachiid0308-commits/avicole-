<?php
declare(strict_types=1);
require_once __DIR__ . '/../htdocs/admin_data_tools.php';

function ad_check(bool $condition, string $message): void
{
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
function ad_csv_file(array $rows, string $prefix = 'admin-data-test-'): string
{
    $stream = fopen('php://temp', 'w+b');
    foreach ($rows as $row) fputcsv($stream, $row, ';', '"', '');
    rewind($stream);
    $contents = stream_get_contents($stream);
    fclose($stream);
    $path = tempnam(sys_get_temp_dir(), $prefix);
    if (!is_string($contents) || !is_string($path) || file_put_contents($path, $contents) === false) throw new RuntimeException('Unable to create test CSV.');
    return $path;
}

ad_check(admin_data_csv_value('=HYPERLINK("https://example.invalid")') === "'=HYPERLINK(\"https://example.invalid\")" && admin_data_csv_value('-7.12345') === '-7.12345', 'CSV export neutralizes formula-like text without altering negative coordinates');
try { admin_data_xlsx_bytes('Test', ['Valeur'], [[str_repeat('x',32768)]]); ad_check(false, 'oversized Excel cell is rejected rather than truncated'); }
catch (InvalidArgumentException $exception) { ad_check(str_contains($exception->getMessage(),'CSV'), 'oversized Excel cells receive a CSV fallback instruction'); }

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE regions (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE provinces (id INTEGER PRIMARY KEY, region_id INTEGER NOT NULL, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE farms (id INTEGER PRIMARY KEY AUTOINCREMENT, region_id INTEGER NOT NULL, province_id INTEGER NOT NULL, authorization_status TEXT NOT NULL, agreement_number TEXT, authorization_number TEXT, owner_name TEXT, national_id TEXT, farm_name TEXT NOT NULL, latitude REAL NOT NULL, longitude REAL NOT NULL, farm_type TEXT NOT NULL, first_agreement_date TEXT, second_agreement_date TEXT, construction_deadline TEXT)');
$pdo->exec('CREATE TABLE legacy_imports (sha256 TEXT PRIMARY KEY, source_name TEXT NOT NULL, imported_rows INTEGER NOT NULL)');
$pdo->exec('CREATE TABLE control_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, region_id INTEGER NOT NULL, province_id INTEGER NOT NULL, farm_id INTEGER, inspection_type TEXT NOT NULL, inspection_date TEXT NOT NULL, commission_label TEXT, unit_name TEXT NOT NULL, authorization_number TEXT, owner_name TEXT, metadata_json TEXT NOT NULL, conclusion TEXT NOT NULL, created_by INTEGER)');
$pdo->exec('CREATE TABLE control_report_answers (id INTEGER PRIMARY KEY AUTOINCREMENT, report_id INTEGER NOT NULL, item_key TEXT NOT NULL, answer TEXT, observation TEXT)');
$pdo->exec('CREATE TABLE control_report_controllers (id INTEGER PRIMARY KEY AUTOINCREMENT, report_id INTEGER NOT NULL, sort_order INTEGER NOT NULL, full_name TEXT NOT NULL, function_title TEXT, signature_text TEXT)');
$pdo->exec("INSERT INTO regions VALUES (1,'Région Test'),(2,'Autre Région')");
$pdo->exec("INSERT INTO provinces VALUES (11,1,'Province Test'),(21,2,'Province Autre')");

[$regionId, $provinceId] = admin_data_geo_filter($pdo, '', '11');
ad_check($regionId === 1 && $provinceId === 11, 'province-only filter resolves its region');
try { admin_data_geo_filter($pdo, '2', '11'); ad_check(false, 'mismatched region/province is rejected'); }
catch (InvalidArgumentException) { }

$unitMatrix = [admin_data_unit_headers(), ['Région Test','Province Test','Autorisé','','AUT-1','Aïcha; "El Amrani"','AB123456','Ferme Test','33.12345','-7.12345','Pondeuses','2026-01-01','','']];
$xlsxBytes = admin_data_xlsx_bytes('Unités avicoles', $unitMatrix[0], [$unitMatrix[1]]);
$xlsxPath = tempnam(sys_get_temp_dir(), 'admin-data-xlsx-');
file_put_contents($xlsxPath, $xlsxBytes);
try { $roundTrip = admin_data_read_matrix($xlsxPath, 'xlsx'); }
finally { unlink($xlsxPath); }
ad_check($roundTrip === $unitMatrix, 'generated Excel workbook round-trips UTF-8 cells through the ZIP reader');

$canonical = admin_data_rows_to_legacy_csv($roundTrip);
$unitCsv = tempnam(sys_get_temp_dir(), 'admin-unit-import-');
file_put_contents($unitCsv, $canonical);
try {
    $unitResult = import_legacy_csv($pdo, $unitCsv, 'unités.xlsx', ['id'=>2,'role'=>ROLE_ADMINISTRATOR], 1, 11);
} finally { unlink($unitCsv); }
ad_check($unitResult['imported'] === 1 && $unitResult['duplicates'] === 0, 'Excel unit row imports through the existing transactional importer');
$unitsExport = admin_data_export_rows($pdo, 'units', 1, 11);
ad_check(count($unitsExport[1]) === 1 && $unitsExport[1][0][5] === 'Aïcha; "El Amrani"' && $unitsExport[1][0][7] === 'Ferme Test', 'unit export is restricted to the requested province and preserves quoted semicolon cells');
ad_check(count(admin_data_export_rows($pdo, 'units', 2, 21)[1]) === 0, 'unit export returns no rows outside the selected province');

$reportRow = ['Région Test','Province Test','chair','2026-10-29','Ferme Test','AUT-1','Aïcha El Amrani','Commission provinciale','{"responsible":"Inspecteur","address":"Adresse"}','Conforme','{"fence":{"status":"oui","observation":"Clôture vérifiée"}}','[{"name":"Samira Test","function":"Vétérinaire","signature_text":"S. Test"}]'];
$reportCsv = ad_csv_file([admin_data_report_headers(), $reportRow]);
try {
    $reportResult = admin_data_import_control_reports($pdo, $reportCsv, 'rapports.csv', ['id'=>2,'role'=>ROLE_ADMINISTRATOR], 1, 11);
} finally { unlink($reportCsv); }
ad_check($reportResult === ['imported'=>1,'duplicates'=>0], 'control report CSV imports transactionally');
$reports = admin_data_export_rows($pdo, 'controls', 1, 11);
ad_check(count($reports[1]) === 1 && $reports[1][0][0] === 'Région Test' && $reports[1][0][2] === 'chair', 'control report export preserves type and filters geography');
$reportXlsx = admin_data_xlsx_bytes('Rapports', $reports[0], $reports[1]);
$xlsxPath = tempnam(sys_get_temp_dir(), 'admin-report-xlsx-');
file_put_contents($xlsxPath, $reportXlsx);
try { $reportRoundTrip = admin_data_read_matrix($xlsxPath, 'xlsx'); }
finally { unlink($xlsxPath); }
ad_check(count($reportRoundTrip) === 2 && $reportRoundTrip[1][10] === $reports[1][0][10], 'control report Excel export round-trips JSON answers');

$routes = [
    'import_export.php' => "if ((\$user['role'] ?? '') !== ROLE_ADMINISTRATOR)",
    'users.php' => 'if (!$isAdmin) { http_response_code(403)',
    'user_edit.php' => "if ((\$user['role'] ?? '') !== ROLE_ADMINISTRATOR)",
    'navigation.php' => "if ((\$user['role'] ?? '') === ROLE_ADMINISTRATOR) \$navigationItems['import_export.php']",
];
foreach ($routes as $file => $guard) {
    $source = file_get_contents(__DIR__ . '/../htdocs/' . $file);
    ad_check(is_string($source) && str_contains($source, $guard), $file . ' has the server-side administrator/navigation guard');
}

echo "OK: admin import/export CSV + XLSX, regional/provincial filters, and administrator route guards\n";
