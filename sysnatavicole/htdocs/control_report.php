<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
require_once __DIR__ . '/controls_domain.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('Rapport introuvable.'); }
[$scope, $params] = scope_clause($user, 'cr');
$params[':id'] = $id;
$stmt = db()->prepare("SELECT cr.*, r.name AS region_name, p.name AS province_name FROM control_reports cr JOIN regions r ON r.id=cr.region_id JOIN provinces p ON p.id=cr.province_id WHERE cr.id=:id AND {$scope} LIMIT 1");
$stmt->execute($params);
$report = $stmt->fetch();
if (!$report) { http_response_code(404); exit('Rapport introuvable ou non autorisé.'); }
$answerStmt = db()->prepare('SELECT item_key, answer, observation FROM control_report_answers WHERE report_id=:report_id');
$answerStmt->execute([':report_id' => $id]);
$answers = [];
foreach ($answerStmt->fetchAll() as $answer) $answers[$answer['item_key']] = ['status' => $answer['answer'] ?? '', 'observation' => $answer['observation'] ?? ''];
$controllerStmt = db()->prepare('SELECT full_name, function_title, signature_text FROM control_report_controllers WHERE report_id=:report_id ORDER BY sort_order, id');
$controllerStmt->execute([':report_id' => $id]);
$controllers = array_map(static fn(array $row): array => ['name' => $row['full_name'], 'function' => $row['function_title'], 'signature_text' => $row['signature_text']], $controllerStmt->fetchAll());
$report['type'] = $report['inspection_type'];
$report['metadata'] = $report['metadata_json'];
$word = (string)($_GET['format'] ?? '') === 'word';
if ($word && !can_download_word_reports($user)) { http_response_code(403); exit('Le téléchargement en format Word est réservé à l’administrateur.'); }
$html = control_report_html($report, $answers, $controllers, $word);
if (!$word) {
    $photoPage=$report['inspection_type']==='transport'?'control_photos.php':'control_unit_photos.php';
    $photoLabel=$report['inspection_type']==='transport'?'Photos du véhicule':'Photos de la visite';
    $html=str_replace('<div class="toolbar">','<div class="toolbar"><a href="'.$photoPage.'?id='.(int)$id.'">'.$photoLabel.'</a>',$html);
}
if (!$word && !can_print_reports($user)) {
    $html = str_replace('<button onclick="window.print()" type="button">Imprimer le rapport</button>', '', $html);
    $html = preg_replace('/<a href="\\?id=\\d+&format=word">Télécharger le rapport Word<\/a>/', '', $html) ?? $html;
    $html = str_replace('</head>', '<style>@media print{html,body{display:none!important;visibility:hidden!important}}</style></head>', $html);
} elseif (!$word && !can_download_word_reports($user)) {
    $html = preg_replace('/<a href="\\?id=\\d+&format=word">Télécharger le rapport Word<\/a>/', '', $html) ?? $html;
}
if (!$word && can_manage_controls($user)) {
    $management = '<a href="control_form.php?id=' . (int)$id . '">Modifier la fiche</a><form method="post" action="delete_control.php" onsubmit="return confirm(\'Supprimer définitivement cette fiche de contrôle ?\')">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$id . '"><button type="submit">Supprimer</button></form>';
    $html = str_replace('<div class="toolbar">', '<div class="toolbar">' . $management, $html);
}
if ($word) {
    $safeType = preg_replace('/[^a-z0-9-]/i', '-', (string)$report['inspection_type']);
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport-controle-' . $safeType . '-' . (int)$report['id'] . '.doc"');
    header('X-Content-Type-Options: nosniff');
} else {
    header('Content-Type: text/html; charset=utf-8');
}
echo $html;
