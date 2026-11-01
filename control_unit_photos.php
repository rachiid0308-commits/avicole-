<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user=require_auth();
require_once __DIR__ . '/controls_domain.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if (!$id||$id<1) { http_response_code(404); exit('Rapport introuvable.'); }
[$scope,$params]=scope_clause($user,'cr'); $params[':id']=$id;
$stmt=db()->prepare("SELECT cr.id,cr.inspection_type,cr.inspection_date,cr.unit_name,r.name AS region_name,p.name AS province_name FROM control_reports cr JOIN regions r ON r.id=cr.region_id JOIN provinces p ON p.id=cr.province_id WHERE cr.id=:id AND {$scope} LIMIT 1");
$stmt->execute($params); $report=$stmt->fetch();
if (!$report||$report['inspection_type']==='transport') { http_response_code(404); exit('Rapport de visite introuvable ou non autorisé.'); }
$photos=[]; $schemaMissing=false;
try {
    $photoStmt=db()->prepare('SELECT photo_slot FROM control_report_unit_photos WHERE report_id=:id ORDER BY photo_slot');
    $photoStmt->execute([':id'=>$id]); $photos=array_map('intval',$photoStmt->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $exception) {
    $message=strtolower($exception->getMessage());
    $tableMissing=str_contains($message,'control_report_unit_photos')&&(str_contains($message,'no such table')||str_contains($message,"doesn't exist")||str_contains($message,'does not exist'));
    if (!$tableMissing) throw $exception;
    http_response_code(503); $schemaMissing=true;
}
$canManage=can_manage_controls($user);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><title>Photos de la visite — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="stylesheet" href="assets/controls.css"><link rel="stylesheet" href="assets/transport-controls.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header><div class="brand"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><div><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></div></div><?php require __DIR__ . '/navigation.php'; ?></header>
<main><div class="page-heading"><div><h1>Photos de la visite</h1><p class="help"><?=e($report['unit_name']?:'Unité avicole')?> · <?=e($report['region_name'])?> · <?=e($report['province_name'])?> · <?=e(date('d/m/Y',strtotime((string)$report['inspection_date'])))?></p></div><div class="actions"><a class="btn secondary" href="control_report.php?id=<?=(int)$id?>">← Retour au rapport</a><a class="btn secondary" href="controls.php">Rapports de contrôle</a><?php if($canManage): ?><a class="btn" href="control_form.php?id=<?=(int)$id?>#unit-photos">Modifier les photos</a><?php endif; ?></div></div>
<?php if($schemaMissing): ?><section class="card controls-setup" role="alert"><h2>Stockage des photos non installé</h2><p>Importez le script de migration avant de gérer les photos des visites.</p><a class="btn secondary" href="database/profile_roles_and_unit_photos.sql" download>Télécharger la migration</a></section><?php else: ?><section class="card"><h2>Photos enregistrées</h2><?php if(!$photos): ?><p class="help">Aucune photo n’est enregistrée pour cette visite.</p><?php else: ?><div class="control-report-photo-grid"><?php foreach($photos as $photoSlot): ?><figure class="control-report-photo"><figcaption>Photo <?=$photoSlot?></figcaption><a href="control_unit_photo.php?id=<?=(int)$id?>&amp;slot=<?=$photoSlot?>" target="_blank" rel="noopener"><img loading="lazy" src="control_unit_photo.php?id=<?=(int)$id?>&amp;slot=<?=$photoSlot?>" alt="Photo <?=$photoSlot?> de la visite de <?=e($report['unit_name'])?>"></a></figure><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?></main>
<footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer><script src="assets/idle-timeout.js" defer></script></body></html>
