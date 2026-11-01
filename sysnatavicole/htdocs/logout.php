<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
start_session();
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['idle'] ?? '') === '1') {
    clear_current_session();
    header('Location: login.php?expired=1');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Méthode non autorisée.'); }
verify_csrf();
clear_current_session();
header('Location: login.php');
