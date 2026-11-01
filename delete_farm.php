<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
if (!can_manage_farm($user)) { http_response_code(403); exit('Profil en consultation seule.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Méthode non autorisée.'); }
verify_csrf();
$id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$farm = $id ? farm_by_id($id, $user) : null;
if (!$farm) { http_response_code(404); exit('Unité introuvable ou non autorisée.'); }
$stmt = db()->prepare('DELETE FROM farms WHERE id=:id');
$stmt->execute([':id'=>$id]);
flash('Unité avicole supprimée.');
header('Location: units.php');
