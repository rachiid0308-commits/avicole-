<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
require_once __DIR__ . '/controls_domain.php';
require_control_writer($user);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Méthode non autorisée.'); }
verify_csrf();
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Identifiant de rapport invalide.'); }
[$scope, $params] = scope_clause($user, 'cr');
$params[':id'] = $id;
$stmt = db()->prepare("SELECT cr.id, cr.inspection_type FROM control_reports cr WHERE cr.id=:id AND {$scope} LIMIT 1");
$stmt->execute($params);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) { http_response_code(404); exit('Rapport introuvable ou non autorisé.'); }
$stmt = db()->prepare('DELETE FROM control_reports WHERE id=:id');
$stmt->execute([':id' => $id]);
flash('La fiche de contrôle a été supprimée.');
$reportListPage = $report['inspection_type'] === 'transport' ? 'vehicle_controls.php' : 'controls.php';
header('Location: ' . $reportListPage);
exit;
