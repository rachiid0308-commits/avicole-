<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
// L’import de données est réservé à l’administrateur et s’effectue depuis l’outil unifié.
if (($user['role'] ?? '') !== ROLE_ADMINISTRATOR) {
    http_response_code(403);
    exit('L’import des données est réservé à l’administrateur.');
}
header('Location: import_export.php?dataset=units', true, 302);
exit;
