<?php
require __DIR__ . '/control_http_test_impl.php';
exit;

require_once __DIR__ . '/../htdocs/controls_domain.php';

function web_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function request_url(string $url, string $sessionId, ?array $post = null): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIE => 'aviculture_session=' . $sessionId,
        CURLOPT_TIMEOUT => 5,
    ]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($curl);
    if (!is_string($raw)) throw new RuntimeException('HTTP request failed: ' . curl_error($curl));
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($curl);
    return [$status, $headers, $body];
}
function make_session(string $path, int $userId): string
{
    session_save_path($path);
    session_name('aviculture_session');
    $id = 'ctrltestsession' . $userId . str_repeat('x', 12);
    session_id($id);
    session_start();
    $_SESSION = ['user_id' => $userId, 'last_activity' => time(), 'csrf' => bin2hex(random_bytes(32))];
    session_write_close();
    return $id;
}

$base = rtrim((string)(getenv('TMPDIR') ?: sys_get_temp_dir()), '/\\') . DIRECTORY_SEPARATOR . 'control-module-http-test';
@mkdir($base, 0777, true);
$sessionDir = $base . DIRECTORY_SEPARATOR . 'sessions';
@mkdir($sessionDir, 0777, true);
$dbPath = $base . DIRECTORY_SEPARATOR . 'fixture.sqlite';
@unlink($dbPath);
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE regions (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE provinces (id INTEGER PRIMARY KEY, region_id INTEGER NOT NULL, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, full_name TEXT, professional_title TEXT, mobile_phone TEXT, role TEXT, region_id INTEGER, province_id INTEGER, active INTEGER DEFAULT 1, last_login_at TEXT)');
$pdo->exec('CREATE TABLE farms (id INTEGER PRIMARY KEY, region_id INTEGER NOT NULL, province_id INTEGER NOT NULL, authorization_status TEXT, authorization_number TEXT, owner_name TEXT, farm_name TEXT NOT NULL, farm_type TEXT NOT NULL)');
$pdo->exec('CREATE TABLE control_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, region_id INTEGER NOT NULL, province_id INTEGER NOT NULL, farm_id INTEGER, inspection_type TEXT NOT NULL, inspection_date TEXT NOT NULL, commission_label TEXT, unit_name TEXT NOT NULL, authorization_number TEXT, owner_name TEXT, metadata_json TEXT NOT NULL, conclusion TEXT NOT NULL, created_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$pdo->exec('CREATE TABLE control_report_answers (id INTEGER PRIMARY KEY AUTOINCREMENT, report_id INTEGER NOT NULL, item_key TEXT NOT NULL, answer TEXT, observation TEXT)');
$pdo->exec('CREATE TABLE control_report_controllers (id INTEGER PRIMARY KEY AUTOINCREMENT, report_id INTEGER NOT NULL, sort_order INTEGER NOT NULL, full_name TEXT NOT NULL, function_title TEXT, signature_text TEXT)');
$pdo->exec('CREATE TABLE control_report_photos (report_id INTEGER NOT NULL, view_key TEXT NOT NULL, mime_type TEXT NOT NULL, image_data BLOB NOT NULL, PRIMARY KEY(report_id,view_key))');
$pdo->exec("INSERT INTO regions VALUES (1,'Région test')");
$pdo->exec("INSERT INTO provinces VALUES (10,1,'Province test'), (20,1,'Autre province')");
$pdo->exec("INSERT INTO users (id,email,full_name,role,region_id,province_id,active) VALUES (1,'central@example.test','Compte central','central',NULL,NULL,1),(2,'prov@example.test','Compte provincial','provincial',1,20,1),(3,'regional@example.test','Compte régional','regional',1,NULL,1),(4,'chef@example.test','Chef de service','provincial',1,10,1)");
$pdo->exec("INSERT INTO farms (id,region_id,province_id,authorization_status,authorization_number,owner_name,farm_name,farm_type) VALUES (7,1,10,'Autorisé','AUT-007','Propriétaire test','Ferme test','Chair'),(8,1,20,'Autorisé','AUT-008','Autre propriétaire','Ferme autre','Chair')");
$pdo = null;
$centralSession = make_session($sessionDir, 1);
$provincialSession = make_session($sessionDir, 2);
$regionalSession = make_session($sessionDir, 3);
$chefSession = make_session($sessionDir, 4);
$portSocket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$portSocket) throw new RuntimeException($errstr);
$address = stream_socket_get_name($portSocket, false);
fclose($portSocket);
$port = (int)substr(strrchr($address, ':'), 1);
$php = 'C:/xampp/php/php.exe';
$project = dirname(__DIR__);
$command = [$php, '-d', 'session.save_path=' . $sessionDir, '-S', '127.0.0.1:' . $port, '-t', $project . '/htdocs'];
$environment = getenv();
$environment['DB_DSN'] = 'sqlite:' . str_replace('\\', '/', $dbPath);
$logPath = $base . DIRECTORY_SEPARATOR . 'server.log';
$log = fopen($logPath, 'wb');
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => $log, 2 => $log], $pipes, $project, $environment);
if (!is_resource($process)) throw new RuntimeException('Could not start PHP test server.');
try {
    usleep(700000);
    $url = 'http://127.0.0.1:' . $port;
    [$status, , $initial] = request_url($url . '/control_form.php', $centralSession);
    web_assert($status === 200 && str_contains($initial, 'Région et province'), 'control module page loads for authenticated user');
    [$status, , $regionalStart] = request_url($url . '/control_form.php', $regionalSession);
    web_assert($status === 200 && str_contains($regionalStart, 'name="region_id" value="1"'), 'regional control form retains the assigned region');
    [$status, , $regionalForm] = request_url($url . '/control_form.php?region_id=1&province_id=10&type=chair&farm_id=7', $regionalSession);
    web_assert($status === 200 && str_contains($regionalForm, 'Ferme test') && str_contains($regionalForm, 'AUT-007'), 'regional user can reach a unit without manually resending their region');
    [$status, , $form] = request_url($url . '/control_form.php?region_id=1&province_id=10&type=chair&farm_id=7', $centralSession);
    web_assert($status === 200 && str_contains($form, 'Ferme test') && str_contains($form, 'AUT-007'), 'selected database unit and authorization are shown');
    web_assert(str_contains($form, 'Propriétaire test') && str_contains($form, 'Membres de la commission'), 'owner and controller signature sections are shown');
    web_assert(preg_match('/name="csrf" value="([a-f0-9]+)"/', $form, $csrf) === 1, 'CSRF token is present');
    $payload = [
        'csrf' => $csrf[1], 'region_id' => '1', 'province_id' => '10', 'inspection_type' => 'chair', 'farm_id' => '7',
        'owner_name' => 'Propriétaire test',
        'metadata' => ['inspection_date' => '2026-10-28', 'commission_label' => 'Commission de test', 'responsible' => 'Responsable', 'address' => 'Adresse'],
        'answers' => ['fence' => ['status' => 'oui', 'observation' => 'Réparer <b>la clôture</b>']],
        'conclusion' => 'Suivi requis',
        'controllers' => [['name' => 'Amina El Fassi', 'function' => 'Vétérinaire', 'signature_text' => 'A. El Fassi']],
    ];
    [$status, $headers] = request_url($url . '/control_save.php', $centralSession, $payload);
    web_assert($status === 302 && preg_match('/Location:\s*control_report\.php\?id=(\d+)/i', $headers, $idMatch) === 1, 'saving redirects to the generated report');
    $reportUrl = $url . '/control_report.php?id=' . $idMatch[1];
    [$status, , $report] = request_url($reportUrl, $centralSession);
    web_assert($status === 200 && str_contains($report, 'Réparer &lt;b&gt;la clôture&lt;/b&gt;'), 'observations are escaped in the report');
    web_assert(str_contains($report, '>X</td>') && str_contains($report, 'Amina El Fassi') && str_contains($report, 'A. El Fassi') && str_contains($report, 'Suivi requis'), 'report contains X marks, conclusion, controller and signature');
    [$status, $wordHeaders, $word] = request_url($reportUrl . '&format=word', $centralSession);
    web_assert($status === 200 && stripos($wordHeaders, 'application/msword') !== false && str_contains($word, 'Microsoft Word'), 'Word-compatible .doc download is served');
    [$status] = request_url($reportUrl, $provincialSession);
    web_assert($status === 404, 'provincial user cannot open a report from another province');
    $badPayload = $payload;
    $badPayload['csrf'] = preg_match('/name="csrf" value="([a-f0-9]+)"/', request_url($url . '/control_form.php', $provincialSession)[2], $otherCsrf) ? $otherCsrf[1] : '';
    $badPayload['province_id'] = '10';
    [$status] = request_url($url . '/control_save.php', $provincialSession, $badPayload);
    web_assert($status === 400, 'provincial user cannot submit a report outside their assigned province');
    $maintenancePdo = new PDO('sqlite:' . str_replace('\\', '/', $dbPath));
    $maintenancePdo->exec('DROP TABLE control_reports');
    $maintenancePdo = null;
    [$status, , $setupMessage] = request_url($url . '/controls.php', $centralSession);
    web_assert($status === 503 && str_contains($setupMessage, 'database/controls.sql') && !str_contains($setupMessage, 'href="control_form.php"'), 'missing control schema shows actionable installation guidance without offering an unusable new-report form');
    echo "OK: PHP HTTP workflow (regional scope, save, access scope, report, Word export and setup guidance) passed\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    if (is_resource($log)) fclose($log);
    @unlink($dbPath);
    @unlink($logPath);
    foreach (glob($sessionDir . DIRECTORY_SEPARATOR . 'sess_*') ?: [] as $sessionFile) @unlink($sessionFile);
    @rmdir($sessionDir);
    @rmdir($base);
}
