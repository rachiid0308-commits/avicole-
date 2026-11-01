<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
start_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if (!current_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
echo json_encode(['ok' => true]);
