<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/controls_domain.php';
require_once __DIR__ . '/legacy_import.php';

function admin_data_geo_filter(PDO $pdo, mixed $regionInput, mixed $provinceInput): array
{
    $regionId = trim((string)$regionInput) === '' ? null : filter_var($regionInput, FILTER_VALIDATE_INT);
    $provinceId = trim((string)$provinceInput) === '' ? null : filter_var($provinceInput, FILTER_VALIDATE_INT);
    if ($regionId === false || $provinceId === false || ($regionId !== null && $regionId < 1) || ($provinceId !== null && $provinceId < 1)) {
        throw new InvalidArgumentException('La région ou la province choisie est invalide.');
    }
    if ($regionId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM regions WHERE id=:id');
        $stmt->execute([':id' => $regionId]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Région inconnue.');
    }
    if ($provinceId !== null) {
        $stmt = $pdo->prepare('SELECT region_id FROM provinces WHERE id=:id');
        $stmt->execute([':id' => $provinceId]);
        $provinceRegion = $stmt->fetchColumn();
        if (!$provinceRegion) throw new InvalidArgumentException('Province / préfecture inconnue.');
        if ($regionId !== null && (int)$provinceRegion !== (int)$regionId) throw new InvalidArgumentException('La province ne correspond pas à la région choisie.');
        $regionId = (int)$provinceRegion;
    }
    return [$regionId === null ? null : (int)$regionId, $provinceId === null ? null : (int)$provinceId];
}

function admin_data_unit_headers(): array
{
    return ['Région','Province / préfecture','Statut','N° accord','N° autorisation','Propriétaire','CNIE','Unité avicole','Latitude','Longitude','Type','Date 1er accord','Date 2ème accord','Délai fin construction'];
}

function admin_data_report_headers(): array
{
    return ['region_name','province_name','inspection_type','inspection_date','unit_name','authorization_number','owner_name','commission_label','metadata_json','conclusion','answers_json','controllers_json'];
}

function admin_data_report_rows(PDO $pdo, ?int $regionId, ?int $provinceId): array
{
    $where = [];
    $params = [];
    if ($regionId !== null) { $where[] = 'cr.region_id=:region_id'; $params[':region_id'] = $regionId; }
    if ($provinceId !== null) { $where[] = 'cr.province_id=:province_id'; $params[':province_id'] = $provinceId; }
    $sql = 'SELECT cr.id,cr.region_id,cr.province_id,cr.inspection_type,cr.inspection_date,cr.unit_name,cr.authorization_number,cr.owner_name,cr.commission_label,cr.metadata_json,cr.conclusion,r.name AS region_name,p.name AS province_name FROM control_reports cr JOIN regions r ON r.id=cr.region_id JOIN provinces p ON p.id=cr.province_id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY cr.region_id,cr.province_id,cr.inspection_date,cr.id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    if (!$reports) return [];
    $ids = array_map(static fn(array $report): int => (int)$report['id'], $reports);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $answersByReport = [];
    $answers = $pdo->prepare("SELECT report_id,item_key,answer,observation FROM control_report_answers WHERE report_id IN ({$placeholders}) ORDER BY report_id,item_key");
    $answers->execute($ids);
    foreach ($answers as $answer) $answersByReport[(int)$answer['report_id']][$answer['item_key']] = ['status'=>(string)($answer['answer'] ?? ''),'observation'=>(string)($answer['observation'] ?? '')];
    $controllersByReport = [];
    $controllers = $pdo->prepare("SELECT report_id,full_name,function_title,signature_text FROM control_report_controllers WHERE report_id IN ({$placeholders}) ORDER BY report_id,sort_order,id");
    $controllers->execute($ids);
    foreach ($controllers as $controller) $controllersByReport[(int)$controller['report_id']][] = ['name'=>$controller['full_name'],'function'=>$controller['function_title'],'signature_text'=>$controller['signature_text']];
    $rows = [];
    foreach ($reports as $report) {
        $id = (int)$report['id'];
        $metadata = json_decode((string)$report['metadata_json'], true);
        $rows[] = [
            $report['region_name'], $report['province_name'], $report['inspection_type'], $report['inspection_date'],
            $report['unit_name'], $report['authorization_number'], $report['owner_name'], $report['commission_label'],
            is_array($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}',
            $report['conclusion'],
            json_encode($answersByReport[$id] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($controllersByReport[$id] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
    return $rows;
}

function admin_data_export_rows(PDO $pdo, string $dataset, ?int $regionId, ?int $provinceId): array
{
    if ($dataset === 'controls') return [admin_data_report_headers(), admin_data_report_rows($pdo, $regionId, $provinceId)];
    if ($dataset !== 'units') throw new InvalidArgumentException('Type de données inconnu.');
    $where = [];
    $params = [];
    if ($regionId !== null) { $where[] = 'f.region_id=:region_id'; $params[':region_id'] = $regionId; }
    if ($provinceId !== null) { $where[] = 'f.province_id=:province_id'; $params[':province_id'] = $provinceId; }
    $sql = 'SELECT r.name AS region_name,p.name AS province_name,f.authorization_status,f.agreement_number,f.authorization_number,f.owner_name,f.national_id,f.farm_name,f.latitude,f.longitude,f.farm_type,f.first_agreement_date,f.second_agreement_date,f.construction_deadline FROM farms f JOIN regions r ON r.id=f.region_id JOIN provinces p ON p.id=f.province_id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY f.region_id,f.province_id,f.id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) $rows[] = $row;
    return [admin_data_unit_headers(), $rows];
}

function admin_data_safe_filename(string $name): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $safe = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii === false ? $name : $ascii) ?? 'export');
    return trim($safe, '-') ?: 'export';
}

function admin_data_csv_value(mixed $value): string
{
    $text = (string)($value ?? '');
    if (preg_match('/^[\\x00-\\x20]*[=+@-]/u', $text) && !is_numeric(trim($text))) return "'" . $text;
    return $text;
}

function admin_data_csv_download(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    $out = fopen('php://output', 'wb');
    if ($out === false) throw new RuntimeException('Impossible de préparer le téléchargement CSV.');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_map('admin_data_csv_value', $headers), ';', '"', '');
    foreach ($rows as $row) fputcsv($out, array_map('admin_data_csv_value', $row), ';', '"', '');
    fclose($out);
    exit;
}

function admin_data_excel_column(int $number): string
{
    $name = '';
    while ($number > 0) {
        $number--;
        $name = chr(65 + ($number % 26)) . $name;
        $number = intdiv($number, 26);
    }
    return $name;
}

function admin_data_zip_bytes(array $entries): string
{
    if (!class_exists('ZipArchive') && !class_exists('PharData')) throw new RuntimeException('Aucune extension PHP de création ZIP (ZipArchive ou PharData) n’est disponible.');
    $basePath = tempnam(sys_get_temp_dir(), 'aviculture-xlsx-');
    if (!is_string($basePath)) throw new RuntimeException('Impossible de créer le classeur temporaire.');
    $zipPath = class_exists('ZipArchive') ? $basePath : $basePath . '.zip';
    if ($zipPath !== $basePath && is_file($basePath)) unlink($basePath);
    try {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Impossible de créer le classeur Excel.');
            foreach ($entries as $name => $contents) {
                if (!$zip->addFromString((string)$name, (string)$contents)) throw new RuntimeException('Impossible d’ajouter une feuille au classeur Excel.');
            }
            if (!$zip->close()) throw new RuntimeException('Impossible de finaliser le classeur Excel.');
        } else {
            $zip = new PharData($zipPath, 0, null, Phar::ZIP);
            foreach ($entries as $name => $contents) $zip->addFromString((string)$name, (string)$contents);
            unset($zip);
        }
        $bytes = file_get_contents($zipPath);
        if (!is_string($bytes) || $bytes === '') throw new RuntimeException('Impossible de lire le classeur Excel généré.');
        return $bytes;
    } finally {
        unset($zip);
        if (is_file($zipPath)) unlink($zipPath);
        if ($zipPath !== $basePath && is_file($basePath)) unlink($basePath);
    }
}

function admin_data_xlsx_bytes(string $sheetName, array $headers, array $rows): string
{
    $sheetName = substr(preg_replace('/[^A-Za-z0-9 _-]/u', '_', $sheetName) ?: 'Donnees', 0, 31);
    $xml = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $allRows = array_merge([$headers], $rows);
    $lastColumn = admin_data_excel_column(max(1, count($headers)));
    $lastRow = max(1, count($allRows));
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:' . $lastColumn . $lastRow . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
    foreach ($allRows as $rowIndex => $row) {
        $excelRow = $rowIndex + 1;
        $sheet .= '<row r="' . $excelRow . '">';
        foreach (array_values($row) as $columnIndex => $value) {
            $ref = admin_data_excel_column($columnIndex + 1) . $excelRow;
            $rawText = (string)($value ?? '');
            $length = function_exists('mb_strlen') ? mb_strlen($rawText, 'UTF-8') : strlen($rawText);
            if ($length > 32767) throw new InvalidArgumentException('Une cellule dépasse la limite Excel de 32 767 caractères. Utilisez le format CSV pour cet export.');
            $rawText = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/u', '', $rawText) ?? $rawText;
            $text = $xml($rawText);
            $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData><autoFilter ref="A1:' . $lastColumn . $lastRow . '"/></worksheet>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    return admin_data_zip_bytes([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => $workbook,
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
}

function admin_data_xlsx_download(string $filename, string $sheetName, array $headers, array $rows): never
{
    $bytes = admin_data_xlsx_bytes($sheetName, $headers, $rows);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Content-Length: ' . strlen($bytes));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    echo $bytes;
    exit;
}

function admin_data_zip_read_entries(string $path, array $members): array
{
    $result = [];
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new InvalidArgumentException('Classeur Excel illisible ou endommagé.');
        try {
            if ($zip->numFiles > 80) throw new InvalidArgumentException('Le classeur Excel contient trop de fichiers internes.');
            foreach ($members as $member) {
                $index = $zip->locateName($member);
                if ($index === false) { $result[$member] = null; continue; }
                $stat = $zip->statIndex($index);
                if (!is_array($stat) || (int)($stat['size'] ?? 0) > 30 * 1024 * 1024) throw new InvalidArgumentException('Une feuille du classeur Excel dépasse la taille autorisée.');
                $contents = $zip->getFromIndex($index);
                if (!is_string($contents)) throw new InvalidArgumentException('Classeur Excel illisible ou endommagé.');
                $result[$member] = $contents;
            }
        } finally { $zip->close(); }
        return $result;
    }
    if (!class_exists('PharData')) throw new RuntimeException('Les extensions PHP ZipArchive ou PharData sont nécessaires pour lire un classeur Excel.');
    $basePath = tempnam(sys_get_temp_dir(), 'aviculture-xlsx-in-');
    if (!is_string($basePath)) throw new RuntimeException('Impossible de préparer le classeur Excel.');
    $zipPath = $basePath . '.zip';
    unlink($basePath);
    $zip = null;
    try {
        if (!copy($path, $zipPath)) throw new RuntimeException('Impossible de lire le classeur Excel téléversé.');
        try { $zip = new PharData($zipPath, 0, null, Phar::ZIP); }
        catch (Throwable $exception) { throw new InvalidArgumentException('Classeur Excel illisible ou endommagé.', 0, $exception); }
        if (count($zip) > 80) throw new InvalidArgumentException('Le classeur Excel contient trop de fichiers internes.');
        foreach ($members as $member) {
            if (!isset($zip[$member])) { $result[$member] = null; continue; }
            $entry = $zip[$member];
            if ($entry->getSize() > 30 * 1024 * 1024) throw new InvalidArgumentException('Une feuille du classeur Excel dépasse la taille autorisée.');
            $contents = $entry->getContent();
            if (!is_string($contents)) throw new InvalidArgumentException('Classeur Excel illisible ou endommagé.');
            $result[$member] = $contents;
        }
        return $result;
    } finally {
        unset($zip);
        if (is_file($zipPath)) unlink($zipPath);
    }
}

function admin_data_read_xlsx(string $path): array
{
    if (!class_exists('DOMDocument')) throw new RuntimeException('L’extension PHP DOM est nécessaire pour lire les classeurs Excel.');
    $contents = admin_data_zip_read_entries($path, ['xl/worksheets/sheet1.xml','xl/sharedStrings.xml']);
    $sheetXml = $contents['xl/worksheets/sheet1.xml'] ?? null;
    if (!is_string($sheetXml) || $sheetXml === '') throw new InvalidArgumentException('Le classeur doit contenir une première feuille de calcul simple (sheet1).');
    $shared = [];
    $sharedXml = $contents['xl/sharedStrings.xml'] ?? null;
    if (is_string($sharedXml)) {
        $doc = new DOMDocument();
        $doc->resolveExternals = false;
        $doc->substituteEntities = false;
        if (!$doc->loadXML($sharedXml, LIBXML_NONET | LIBXML_COMPACT)) throw new InvalidArgumentException('La table de chaînes du classeur est invalide.');
        foreach ($doc->getElementsByTagNameNS('*', 'si') as $item) $shared[] = $item->textContent;
    }
    $doc = new DOMDocument();
    $doc->resolveExternals = false;
    $doc->substituteEntities = false;
    if (!$doc->loadXML($sheetXml, LIBXML_NONET | LIBXML_COMPACT)) throw new InvalidArgumentException('La première feuille Excel est invalide.');
    $rows = [];
    foreach ($doc->getElementsByTagNameNS('*', 'row') as $rowNode) {
        if (count($rows) >= 5001) throw new InvalidArgumentException('Un fichier d’import ne peut pas dépasser 5 000 lignes.');
        $cells = [];
        foreach ($rowNode->getElementsByTagNameNS('*', 'c') as $cell) {
            $reference = strtoupper((string)$cell->getAttribute('r'));
            if (!preg_match('/^([A-Z]+)/', $reference, $match)) continue;
            $column = 0;
            foreach (str_split($match[1]) as $letter) $column = $column * 26 + ord($letter) - 64;
            if ($column < 1 || $column > 64) continue;
            $value = '';
            $type = (string)$cell->getAttribute('t');
            if ($type === 'inlineStr') {
                foreach ($cell->getElementsByTagNameNS('*', 'is') as $inline) { $value = $inline->textContent; break; }
            } else {
                foreach ($cell->getElementsByTagNameNS('*', 'v') as $v) { $value = $v->textContent; break; }
                if ($type === 's' && $value !== '' && isset($shared[(int)$value])) $value = $shared[(int)$value];
                if ($type === 'b') $value = $value === '1' ? 'TRUE' : 'FALSE';
            }
            $cells[$column - 1] = $value;
        }
        if (!$cells) continue;
        $max = max(array_keys($cells));
        $row = array_fill(0, $max + 1, '');
        foreach ($cells as $column => $value) $row[$column] = $value;
        $rows[] = $row;
    }
    return $rows;
}

function admin_data_read_matrix(string $path, string $extension): array
{
    if ($extension === 'xlsx') return admin_data_read_xlsx($path);
    if ($extension !== 'csv') throw new InvalidArgumentException('Formats acceptés : CSV ou XLSX.');
    $contents = file_get_contents($path);
    if (!is_string($contents) || $contents === '') throw new InvalidArgumentException('Le fichier CSV est vide ou illisible.');
    if (strlen($contents) > 10 * 1024 * 1024) throw new InvalidArgumentException('Le fichier d’import dépasse 10 Mo.');
    if (str_starts_with($contents, "\xEF\xBB\xBF")) $contents = substr($contents, 3);
    if (preg_match('//u', $contents) !== 1) {
        $converted = iconv('Windows-1252', 'UTF-8//IGNORE', $contents);
        if ($converted === false) throw new InvalidArgumentException('Encodage CSV non reconnu. Utilisez UTF-8 ou Windows-1252.');
        $contents = $converted;
    }
    $firstLine = strtok($contents, "\r\n") ?: '';
    $counts = [';'=>substr_count($firstLine,';'), ','=>substr_count($firstLine,','), "\t"=>substr_count($firstLine,"\t")];
    arsort($counts);
    $delimiter = (string)array_key_first($counts);
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false) throw new RuntimeException('Impossible de préparer le CSV.');
    fwrite($stream, $contents);
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
        if ($row === [null] || $row === []) continue;
        $rows[] = array_map(static fn(mixed $value): string => trim((string)$value), $row);
        if (count($rows) > 5001) { fclose($stream); throw new InvalidArgumentException('Un fichier d’import ne peut pas dépasser 5 000 lignes.'); }
    }
    fclose($stream);
    return $rows;
}

function admin_data_header_key(string $value): string
{
    $value = strtr(trim($value), [
        'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','À'=>'A','Á'=>'A','Â'=>'A','Ä'=>'A','Ã'=>'A','Å'=>'A',
        'æ'=>'ae','Æ'=>'AE','ç'=>'c','Ç'=>'C','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','ñ'=>'n','Ñ'=>'N',
        'ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','Ò'=>'O','Ó'=>'O','Ô'=>'O','Ö'=>'O','Õ'=>'O','œ'=>'oe','Œ'=>'OE',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','ý'=>'y','ÿ'=>'y','Ý'=>'Y','Ÿ'=>'Y','ß'=>'ss',
    ]);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $text = strtolower($ascii === false ? $value : $ascii);
    return trim(preg_replace('/[^a-z0-9]+/', '_', $text) ?? '', '_');
}

function admin_data_rows_to_legacy_csv(array $matrix): string
{
    if (count($matrix) < 2) throw new InvalidArgumentException('Le fichier unités doit contenir une ligne d’en-tête et au moins une unité.');
    if (count($matrix[0]) !== 14) throw new InvalidArgumentException('Le modèle unités doit comporter 14 colonnes.');
    $headers = array_map('admin_data_header_key', $matrix[0]);
    if (($headers[0] ?? '') !== 'region' || ($headers[2] ?? '') !== 'statut') throw new InvalidArgumentException('En-tête invalide : téléchargez le modèle CSV/XLSX unités depuis cette page.');
    $temp = fopen('php://temp', 'w+b');
    if ($temp === false) throw new RuntimeException('Impossible de préparer les données unités.');
    foreach ($matrix as $row) {
        if (count($row) !== 14) { fclose($temp); throw new InvalidArgumentException('Chaque ligne unités doit comporter exactement 14 colonnes.'); }
        fputcsv($temp, $row, ';', '"', '');
    }
    rewind($temp);
    $content = stream_get_contents($temp);
    fclose($temp);
    if (!is_string($content)) throw new RuntimeException('Impossible de lire les données unités préparées.');
    return $content;
}

function admin_data_normalize_date(string $value, int $line): string
{
    $value = trim($value);
    foreach (['!Y-m-d'=>'Y-m-d','!d/m/Y'=>'d/m/Y'] as $format=>$display) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date && $date->format($display) === $value) return $date->format('Y-m-d');
    }
    throw new InvalidArgumentException("Ligne {$line}: date invalide (utilisez AAAA-MM-JJ ou JJ/MM/AAAA).");
}

function admin_data_import_control_reports(PDO $pdo, string $path, string $sourceName, array $user, ?int $targetRegionId, ?int $targetProvinceId): array
{
    $hash = hash_file('sha256', $path);
    if (!is_string($hash)) throw new RuntimeException('Empreinte de fichier impossible à calculer.');
    $check = $pdo->prepare('SELECT imported_rows FROM legacy_imports WHERE sha256=:hash');
    $check->execute([':hash'=>$hash]);
    if ($check->fetchColumn() !== false) throw new InvalidArgumentException('Ce fichier a déjà été importé (même empreinte SHA-256).');
    $matrix = admin_data_read_matrix($path, strtolower(pathinfo($sourceName, PATHINFO_EXTENSION)));
    if (count($matrix) < 2) throw new InvalidArgumentException('Le fichier rapports doit contenir un en-tête et au moins un rapport.');
    $header = array_map('admin_data_header_key', $matrix[0]);
    $required = ['region_name','province_name','inspection_type','inspection_date','unit_name','commission_label','metadata_json','conclusion','answers_json','controllers_json'];
    $positions = [];
    foreach ($required as $key) {
        $position = array_search($key, $header, true);
        if ($position === false) throw new InvalidArgumentException('Colonne manquante dans le fichier rapports : '.$key.'. Téléchargez le modèle depuis cette page.');
        $positions[$key] = $position;
    }
    foreach (['authorization_number','owner_name'] as $key) $positions[$key] = array_search($key, $header, true);
    $regions = [];
    foreach ($pdo->query('SELECT id,name FROM regions') as $region) $regions[admin_data_header_key($region['name'])] = ['id'=>(int)$region['id'],'name'=>$region['name']];
    $provinces = [];
    foreach ($pdo->query('SELECT p.id,p.region_id,p.name,r.name AS region_name FROM provinces p JOIN regions r ON r.id=p.region_id') as $province) {
        $provinces[admin_data_header_key($province['region_name']).'|'.admin_data_header_key($province['name'])] = ['id'=>(int)$province['id'],'region_id'=>(int)$province['region_id'],'region_name'=>$province['region_name'],'name'=>$province['name']];
    }
    $types = control_checklists();
    $prepared = [];
    foreach (array_slice($matrix, 1) as $index=>$row) {
        $line = $index + 2;
        $get = static function(string $key) use ($positions,$row): string {
            $position = $positions[$key] ?? false;
            return $position === false ? '' : trim((string)($row[$position] ?? ''));
        };
        $region = $regions[admin_data_header_key($get('region_name'))] ?? null;
        if (!$region) throw new InvalidArgumentException("Ligne {$line}: région inconnue.");
        $province = $provinces[admin_data_header_key($region['name']).'|'.admin_data_header_key($get('province_name'))] ?? null;
        if (!$province) throw new InvalidArgumentException("Ligne {$line}: province inconnue ou non rattachée à cette région.");
        if ($targetRegionId !== null && $region['id'] !== $targetRegionId) throw new InvalidArgumentException("Ligne {$line}: région différente de la région sélectionnée.");
        if ($targetProvinceId !== null && $province['id'] !== $targetProvinceId) throw new InvalidArgumentException("Ligne {$line}: province différente de la province sélectionnée.");
        $type = $get('inspection_type');
        if (!isset($types[$type])) throw new InvalidArgumentException("Ligne {$line}: type de contrôle inconnu.");
        $metadata = json_decode($get('metadata_json'), true);
        $answers = json_decode($get('answers_json'), true);
        $controllers = json_decode($get('controllers_json'), true);
        if (!is_array($metadata) || !is_array($answers) || !is_array($controllers)) throw new InvalidArgumentException("Ligne {$line}: les colonnes JSON du rapport sont invalides.");
        $date = admin_data_normalize_date($get('inspection_date'), $line);
        if ($type !== 'transport' && $get('unit_name') === '') throw new InvalidArgumentException("Ligne {$line}: le nom de l’unité est obligatoire.");
        $farmId = null;
        if ($type !== 'transport' && $get('unit_name') !== '') {
            $farm = $pdo->prepare('SELECT id FROM farms WHERE region_id=:region AND province_id=:province AND farm_name=:name ORDER BY id LIMIT 2');
            $farm->execute([':region'=>$region['id'],':province'=>$province['id'],':name'=>$get('unit_name')]);
            $farmIds = $farm->fetchAll(PDO::FETCH_COLUMN);
            if (count($farmIds) > 1) throw new InvalidArgumentException("Ligne {$line}: plusieurs unités portent ce nom dans la province; import annulé pour éviter une association ambiguë.");
            if ($farmIds) $farmId = (int)$farmIds[0];
        }
        $report = [
            'type'=>$type,'region_id'=>$region['id'],'province_id'=>$province['id'],'farm_id'=>$farmId,
            'inspection_date'=>$date,'commission_label'=>$get('commission_label'),'unit_name'=>$get('unit_name'),
            'authorization_number'=>$get('authorization_number'),'owner_name'=>$get('owner_name'),
            'metadata'=>$metadata,'conclusion'=>$get('conclusion'),'created_by'=>(int)$user['id'],
        ];
        $prepared[] = ['report'=>$report,'answers'=>$answers,'controllers'=>$controllers];
    }
    if (!$prepared) throw new InvalidArgumentException('Aucun rapport à importer.');
    $pdo->beginTransaction();
    try {
        $imported = 0;
        $duplicates = 0;
        $duplicateCheck = $pdo->prepare("SELECT id FROM control_reports WHERE region_id=:region AND province_id=:province AND inspection_type=:type AND inspection_date=:date AND unit_name=:unit AND COALESCE(authorization_number,'')=:authorization AND COALESCE(owner_name,'')=:owner AND COALESCE(commission_label,'')=:commission AND metadata_json=:metadata AND conclusion=:conclusion LIMIT 1");
        foreach ($prepared as $entry) {
            $report = $entry['report'];
            $duplicateCheck->execute([
                ':region'=>$report['region_id'],':province'=>$report['province_id'],':type'=>$report['type'],':date'=>$report['inspection_date'],
                ':unit'=>$report['unit_name'],':authorization'=>$report['authorization_number'],':owner'=>$report['owner_name'],':commission'=>$report['commission_label'],
                ':metadata'=>json_encode($report['metadata'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),':conclusion'=>$report['conclusion'],
            ]);
            if ($duplicateCheck->fetchColumn() !== false) { $duplicates++; continue; }
            control_create($pdo,$report,$entry['answers'],$entry['controllers']);
            $imported++;
        }
        $log = $pdo->prepare('INSERT INTO legacy_imports (sha256,source_name,imported_rows) VALUES (:hash,:name,:rows)');
        $log->execute([':hash'=>$hash,':name'=>substr(basename($sourceName),0,255),':rows'=>$imported]);
        $pdo->commit();
        return ['imported'=>$imported,'duplicates'=>$duplicates];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
