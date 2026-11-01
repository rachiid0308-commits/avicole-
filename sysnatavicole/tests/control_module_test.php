<?php
declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$domainFile = __DIR__ . '/../htdocs/controls_domain.php';
check(is_file($domainFile), 'control domain module exists');
require_once $domainFile;

$checklists = control_checklists();
check(array_keys($checklists) === ['chair', 'reproducteurs', 'pondeuses', 'transport'], 'all four requested inspection types are available');
check(count($checklists['chair']['items']) >= 20, 'broiler template has the source checklist criteria');
check(count($checklists['reproducteurs']['items']) >= 25, 'breeder template has the source checklist criteria');
check(count($checklists['pondeuses']['items']) >= 30, 'layers template has the source checklist criteria');
check(count($checklists['transport']['items']) >= 14, 'transport template has the source checklist criteria');

check(control_mark('oui', 'oui') === 'X', 'oui response prints an X in the oui column');
check(control_mark('oui', 'non') === '', 'oui response leaves the non column blank');
check(control_mark('non', 'non') === 'X', 'non response prints an X in the non column');
check(control_mark('non', 'oui') === '', 'non response leaves the oui column blank');

$checklist = $checklists['chair'];
$firstId = array_key_first($checklist['items']);
$html = control_report_html([
    'type' => 'chair', 'unit_name' => 'Ferme Exemple', 'region_name' => 'Région Exemple',
    'province_name' => 'Province Exemple', 'inspection_date' => '2026-10-28',
    'commission' => 'Commission A', 'conclusion' => 'À corriger', 'metadata' => [],
], [
    $firstId => ['status' => 'oui', 'observation' => 'Texte observation'],
], [['name' => 'Agent Contrôle', 'function' => 'Vétérinaire']], false);
check(str_contains($html, '<th>Oui</th>') && str_contains($html, '<th>Non</th>'), 'report displays separate oui/non columns');
check(str_contains($html, '>X</td>'), 'report prints an X rather than a checkbox');
check(str_contains($html, 'Texte observation'), 'report includes free-text observations');
check(str_contains($html, 'Agent Contrôle'), 'report includes named controllers');
check(str_contains($html, 'Province Exemple'), 'report identifies the chosen province');
check(str_contains($html, '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'), 'standalone control report scales to the real mobile viewport');
check(str_contains($html, '<div class="checklist-scroll"><table class="checklist">') && str_contains($html, '.checklist-scroll{width:100%;max-width:100%;overflow-x:auto;'), 'mobile checklist scrolls inside its own bounded wrapper');
check(str_contains($html, '@media screen{body{width:min(1600px,calc(100% - 64px));max-width:none;margin:24px auto'), 'screen report expands to use desktop width');
check(str_contains($html, '@page{size:A4 portrait;margin:2mm}@media print{'), 'screen layout refinements preserve the compact A4 print rules');
$controlsPage = file_get_contents(__DIR__ . '/../htdocs/controls.php') ?: '';
$reportsCss = file_get_contents(__DIR__ . '/../htdocs/assets/control-reports.css') ?: '';
check(str_contains($controlsPage, 'assets/control-reports.css'), 'control list loads its report layout overrides');
check(str_contains($controlsPage,'LIMIT :limit OFFSET :offset')&&str_contains($controlsPage,'Pagination des rapports véhicules')&&str_contains($controlsPage,'N° immatriculation')&&str_contains($reportsCss,'.registration-cell{min-width:128px'),'unit and vehicle report lists paginate 20 rows and keep all registration digits visible');
check(str_contains($controlsPage, 'controls-list-main') && str_contains($controlsPage, 'controls-list-footer') && str_contains($reportsCss, 'max-width:1840px'), 'control reports list and footer use a wider aligned desktop workspace');
check(str_contains($controlsPage, 'Photos du véhicule') && str_contains($controlsPage, 'control_photos.php?id=') && str_contains($controlsPage, 'control_form.php?id=') && str_contains($controlsPage, 'transport-reports'), 'transport list includes a dedicated photo viewing and upload column');
check(str_contains($controlsPage, 'assets/transport-controls.css'), 'transport photo layout styles load on the report list');
$photoPage = file_get_contents(__DIR__ . '/../htdocs/control_photos.php') ?: '';
$photoEndpoint = file_get_contents(__DIR__ . '/../htdocs/control_photo.php') ?: '';
$photoSql = file_get_contents(__DIR__ . '/../htdocs/database/controls.sql') ?: '';
$fullSchema = file_get_contents(__DIR__ . '/../htdocs/database/schema.sql') ?: '';
check(str_contains($photoPage, 'scope_clause') && str_contains($photoEndpoint, 'scope_clause') && str_contains($photoEndpoint, 'Content-Disposition: inline'), 'photo gallery and image endpoint enforce report scope and inline image delivery');
$transportCss = file_get_contents(__DIR__ . '/../htdocs/assets/transport-controls.css') ?: '';
check(str_contains($photoPage, 'href="vehicle_controls.php">← Retour aux rapports véhicules</a>') && str_contains($photoPage, 'can_manage_controls($user)') && str_contains($transportCss, '.control-report-photo img{display:block;width:2cm;height:2cm'), 'vehicle photo page returns to controls and shows compact 2 cm thumbnails while preserving scoped viewing');
check(str_contains($photoSql, 'CREATE TABLE IF NOT EXISTS control_report_photos') && str_contains($fullSchema, 'CREATE TABLE IF NOT EXISTS control_report_photos'), 'photo storage is included in both migration and fresh-install schema');
check(str_contains($reportsCss, '.control-report-group .tablewrap .units-table{display:table;width:100%;min-width:0;table-layout:fixed}') && str_contains($reportsCss, 'overflow-x:auto;-webkit-overflow-scrolling:touch'), 'report list fills desktop width and remains touch-scrollable on smaller screens');
check(str_contains($html, 'width:min(1600px,calc(100% - 64px))'), 'standalone screen reports use most available desktop width');

$word = control_report_html([
    'type' => 'chair', 'unit_name' => 'Ferme Exemple', 'region_name' => 'Région Exemple',
    'province_name' => 'Province Exemple', 'inspection_date' => '2026-10-28',
    'commission' => 'Commission A', 'conclusion' => '', 'metadata' => [],
], [], [], true);
check(str_contains($word, 'Microsoft Word') && str_contains($word, 'Ferme Exemple'), 'Word export contains a Word-compatible report');

echo "OK: contrôle avicole tests passed\n";
