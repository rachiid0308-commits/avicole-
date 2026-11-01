<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
require_once __DIR__ . '/controls_domain.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('Rapport introuvable.'); }
[$scope, $params] = scope_clause($user, 'cr');
$params[':id'] = $id;
$reportStmt = db()->prepare("SELECT cr.id, cr.inspection_type, cr.inspection_date, cr.unit_name, cr.owner_name, cr.metadata_json, r.name AS region_name, p.name AS province_name FROM control_reports cr JOIN regions r ON r.id=cr.region_id JOIN provinces p ON p.id=cr.province_id WHERE cr.id=:id AND {$scope} LIMIT 1");
$reportStmt->execute($params);
$report = $reportStmt->fetch();
if (!$report || $report['inspection_type'] !== 'transport') { http_response_code(404); exit('Rapport de transport introuvable ou non autorisé.'); }
$schemaMissing = false;
$photos = [];
try {
    $photoStmt = db()->prepare('SELECT view_key FROM control_report_photos WHERE report_id=:id');
    $photoStmt->execute([':id' => $id]);
    $savedViews = $photoStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach (vehicle_photo_views() as $view => $_label) if (in_array($view, $savedViews, true)) $photos[$view] = true;
} catch (PDOException $exception) {
    $message = strtolower($exception->getMessage());
    $tableMissing = str_contains($message, 'control_report_photos') && (str_contains($message, 'no such table') || str_contains($message, "doesn't exist") || str_contains($message, 'does not exist'));
    if (!$tableMissing) throw $exception;
    http_response_code(503);
    $schemaMissing = true;
}
$metadata = json_decode((string)$report['metadata_json'], true);
$metadata = is_array($metadata) ? $metadata : [];
$plate = moroccan_registration_display((string)($metadata['registration'] ?? ''));
$product = transport_product_label((string)($metadata['products'] ?? ''));
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><title>Photos du véhicule — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="stylesheet" href="assets/controls.css"><link rel="stylesheet" href="assets/transport-controls.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header><div class="brand"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><div><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></div></div><?php require __DIR__ . '/navigation.php'; ?></header>
<main><div class="page-heading"><div><h1>Photos du véhicule</h1><p class="help"><?=e($report['owner_name'] ?: 'Transport')?> · <?=e($plate ?: 'Immatriculation non renseignée')?> · <?=e($product ?: 'Produit non renseigné')?></p><p class="help"><?=e($report['region_name'])?> · <?=e($report['province_name'])?> · Contrôle du <?=e(date('d/m/Y', strtotime((string)$report['inspection_date'])))?></p></div><div class="actions"><a class="btn secondary" href="vehicle_controls.php">← Retour aux rapports véhicules</a><?php if (can_manage_controls($user)): ?><a class="btn" href="control_form.php?id=<?=(int)$id?>#vehicle-photos">Ajouter / remplacer des photos</a><?php endif; ?></div></div>
<?php if ($schemaMissing): ?><section class="card controls-setup" role="alert"><h2>Le stockage des photos n’est pas encore installé</h2><p>Importez une fois le fichier <code>database/controls.sql</code> dans la base du site, puis rechargez cette page.</p><a class="btn secondary" href="database/controls.sql" download>Télécharger le script SQL</a></section><?php else: ?><section class="card"><h2>Vues du véhicule</h2><?php if (!$photos): ?><p class="help">Aucune photo n’a encore été ajoutée à ce rapport.</p><?php endif; ?><div class="control-report-photo-grid"><?php foreach (vehicle_photo_views() as $view => $label): ?><figure class="control-report-photo"><figcaption><?=e($label)?></figcaption><?php if (isset($photos[$view])): ?><a href="control_photo.php?id=<?=(int)$id?>&amp;view=<?=e($view)?>" target="_blank" rel="noopener"><img loading="lazy" src="control_photo.php?id=<?=(int)$id?>&amp;view=<?=e($view)?>" alt="Photo du véhicule — <?=e($label)?>"></a><?php else: ?><div class="help">Photo non enregistrée</div><?php endif; ?></figure><?php endforeach; ?></div></section><?php endif; ?></main>
<footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer><script src="assets/idle-timeout.js" defer></script></body></html>
