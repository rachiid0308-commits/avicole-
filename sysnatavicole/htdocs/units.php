<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
$filters = [
    'region_id' => $_GET['region_id'] ?? '', 'province_id' => $_GET['province_id'] ?? '',
    'type' => $_GET['type'] ?? '', 'status' => $_GET['status'] ?? '', 'q' => trim((string)($_GET['q'] ?? '')),
];
$sort = (string)($_GET['sort'] ?? 'region');
$requestedView = (string)($_GET['view'] ?? 'region');
$view = in_array($requestedView, ['global','region','province'], true) ? $requestedView : 'region';
$geo = geography_rows($user);
if (has_region_scope($user) && !has_global_scope($user)) $filters['region_id'] = (string)$user['region_id'];
if (has_province_scope($user)) { $filters['region_id'] = (string)$user['region_id']; $filters['province_id'] = (string)$user['province_id']; }
$rows = list_farms($user, $filters, $sort, $view);
$groupCounts = [];
if ($view !== 'global') foreach ($rows as $row) {
    $key = $view === 'region' ? $row['region_name'] : $row['region_name'].'|'.$row['province_name'];
    $groupCounts[$key] = ($groupCounts[$key] ?? 0) + 1;
}
$totalRows = count($rows);
$perPage = 20;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$pageValue = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$currentPage = $pageValue === false || $pageValue < 1 ? 1 : min($pageValue, $totalPages);
$pageRows = array_slice($rows, ($currentPage - 1) * $perPage, $perPage);
$flash = take_flash();
$groupValues = ['global'=>'Vue globale (sans regroupement)','region'=>'Par région','province'=>'Par province / préfecture'];
$sortValues = ['region'=>'Région','province'=>'Province / préfecture','name'=>'Nom de l’unité','status'=>'Statut','type'=>'Type','date'=>'Date du 1er accord'];
$groupKey = null;
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light"><title>La liste des unités — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header><div class="brand"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><div><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></div></div><?php require __DIR__ . '/navigation.php'; ?></header>
<main>
<?php if ($flash): ?><div class="flash <?=e($flash['type'] === 'error' ? 'error' : '')?>"><?=e($flash['message'])?></div><?php endif; ?>
<div class="page-heading"><div><h1>La liste des unités</h1><p class="help">Consultez et filtrez les unités avicoles accessibles selon votre profil.</p></div><?php if (can_create_farm($user)): ?><a class="btn" href="farm_form.php">➕ Ajouter une unité</a><?php endif; ?></div>
<div class="card"><div class="stats stats-compact"><div class="stat"><b><?=count($rows)?></b>Unité(s) dans le résultat</div></div></div>
<div class="card"><form class="toolbar toolbar-fit" method="get" action="units.php">
<label class="search">Recherche<input name="q" value="<?=e($filters['q'])?>" placeholder="Nom, propriétaire, n° accord…"></label>
<?php if (has_global_scope($user)): ?><label>Région<select name="region_id"><option value="">Toutes</option><?php foreach ($geo['regions'] as $id=>$name): ?><option value="<?=$id?>" <?=$filters['region_id']===(string)$id?'selected':''?>><?=e($name)?></option><?php endforeach; ?></select></label><?php endif; ?>
<?php if (!has_province_scope($user)): ?><label>Province / préfecture<select name="province_id"><option value="">Toutes</option><?php foreach ($geo['rows'] as $g): if ($filters['region_id'] && (int)$filters['region_id'] !== (int)$g['region_id']) continue; ?><option value="<?=$g['province_id']?>" <?=$filters['province_id']===(string)$g['province_id']?'selected':''?>><?=e($g['province_name'])?> · <?=e($g['region_name'])?></option><?php endforeach; ?></select></label><?php endif; ?>
<label>Type<select name="type"><option value="">Tous les types</option><?php foreach (FARM_TYPES as $type): ?><option <?=$filters['type']===$type?'selected':''?>><?=e($type)?></option><?php endforeach; ?></select></label>
<label>Statut<select name="status"><option value="">Tous les statuts</option><?php foreach (FARM_STATUSES as $status): ?><option <?=$filters['status']===$status?'selected':''?>><?=e($status)?></option><?php endforeach; ?></select></label>
<label>Affichage<select name="view"><?php foreach ($groupValues as $value=>$label): ?><option value="<?=e($value)?>" <?=$view===$value?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select></label>
<label>Trier par<select name="sort"><?php foreach ($sortValues as $value=>$label): ?><option value="<?=e($value)?>" <?=$sort===$value?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select></label>
<div class="toolbar-actions"><button class="btn" type="submit">Filtrer / trier</button><a class="btn" href="units.php">Effacer</a></div>
<?php foreach (['region_id','province_id'] as $fixed): if (((has_region_scope($user) && !has_global_scope($user)) && $fixed==='region_id') || (has_province_scope($user) && in_array($fixed,['region_id','province_id'],true))): ?><input type="hidden" name="<?=$fixed?>" value="<?=e($filters[$fixed])?>"><?php endif; endforeach; ?>
</form></div>
<div id="unit-list" class="card"><h2 class="list-title">Unités avicoles</h2>
<?php if (!$rows): ?><p class="help">Aucune donnée ne correspond aux filtres. Une nouvelle base est vide : le site n’importe pas les exemples aléatoires de l’ancienne page HTML.</p><?php else: ?><div class="tablewrap"><table class="units-table"><thead><tr><th>#</th><th>Région</th><th>Province / préfecture</th><th>Statut</th><th>N° accord</th><th>N° autorisation</th><th>Propriétaire</th><th>CNIE</th><th>Unité</th><th>Coordonnées GPS</th><th>Type</th><th>Dates</th><th>Action</th></tr></thead><tbody>
<?php foreach ($pageRows as $i=>$farm): $key = match($view) { 'region' => $farm['region_name'], 'province' => $farm['region_name'].'|'.$farm['province_name'], default => null }; if ($key !== null && $key !== $groupKey): $groupKey=$key; ?><tr class="group"><td colspan="13"><?=e($view==='province' ? $farm['region_name'].' — '.$farm['province_name'] : $farm['region_name'])?> <span class="help">· <?= (int)$groupCounts[$key] ?> unité(s)</span></td></tr><?php endif; ?>
<tr><td class="cell-index" data-label="N°"><?= (($currentPage - 1) * $perPage) + $i + 1 ?></td><td data-label="Région"><?=e($farm['region_name'])?></td><td data-label="Province / préfecture"><?=e($farm['province_name'])?></td><td data-label="Statut"><span class="badge"><?=e($farm['authorization_status'])?></span></td><td data-label="N° accord"><?=e($farm['agreement_number'])?></td><td data-label="N° autorisation"><?=e($farm['authorization_number'])?></td><td data-label="Propriétaire"><?=e($farm['owner_name'])?></td><td data-label="CNIE"><?=e($farm['national_id'])?></td><td class="cell-unit"><?=e($farm['farm_name'])?></td><td data-label="Coordonnées GPS"><a class="coordinate-link" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q=<?=rawurlencode((string)$farm['latitude'])?>,<?=rawurlencode((string)$farm['longitude'])?>"><?=e($farm['latitude'])?>, <?=e($farm['longitude'])?> ↗</a></td><td data-label="Type"><?=e($farm['farm_type'])?></td><?php $timeline=farm_timeline($farm); ?><td class="dates-cell" data-label="Dates"><div><strong>1er accord :</strong> <?=e(format_date_fr($timeline['first']))?></div><div class="<?= $timeline['second_overdue'] ? 'date-overdue' : '' ?>"><strong>2e accord :</strong> <?=e(format_date_fr($timeline['second'] ?? $timeline['second_due']))?><?= !$timeline['second'] ? ' (échéance)' : '' ?></div><div class="<?= $timeline['construction_overdue'] ? 'date-overdue' : '' ?>"><strong>Fin des travaux :</strong> <?=e(format_date_fr($timeline['construction'] ?? $timeline['construction_due']))?><?= !$timeline['construction'] ? ($timeline['construction_due'] ? ' (échéance)' : ' (date du 2e accord requise)') : '' ?></div></td><td class="cell-action"><?php if (can_manage_farm($user)): ?><div class="unit-row-actions"><a class="btn secondary" href="farm_form.php?id=<?=(int)$farm['id']?>">Modifier</a><?php if (($user['role'] ?? '') === ROLE_ADMINISTRATOR): ?><form method="post" action="delete_farm.php" onsubmit="return confirm('Supprimer définitivement cette unité avicole ?');"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$farm['id']?>"><button class="btn danger btn-sm" type="submit">Supprimer</button></form><?php endif; ?></div><?php else: ?><span class="help">Consultation</span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
<div class="list-actions"><?php if (can_import_farm($user)): ?><a class="btn secondary" href="import_export.php?dataset=units">Importer/Exporter</a><?php endif; ?><?php if (can_create_farm($user)): ?><a class="btn" href="farm_form.php">➕ Ajouter une unité</a><?php endif; ?></div></div>
<?php
$previousParams = $_GET; unset($previousParams['export']); $previousParams['page'] = max(1, $currentPage - 1);
$nextParams = $_GET; unset($nextParams['export']); $nextParams['page'] = min($totalPages, $currentPage + 1);
$firstDisplayed = $totalRows === 0 ? 0 : (($currentPage - 1) * $perPage) + 1;
$lastDisplayed = min($currentPage * $perPage, $totalRows);
?>
<nav class="pagination" aria-label="Pagination des unités">
  <span class="pagination-info"><?= $firstDisplayed ?>–<?= $lastDisplayed ?> sur <?= $totalRows ?> unité(s)</span>
  <div class="pagination-actions">
    <?php if ($currentPage > 1): ?><a class="btn secondary" href="?<?=e(http_build_query($previousParams))?>" aria-label="Page précédente">← Précédente</a><?php else: ?><span class="btn secondary is-disabled" aria-disabled="true">← Précédente</span><?php endif; ?>
    <span class="pagination-page">Page <?= $currentPage ?> / <?= $totalPages ?></span>
    <?php if ($currentPage < $totalPages): ?><a class="btn secondary" href="?<?=e(http_build_query($nextParams))?>" aria-label="Page suivante">Suivante →</a><?php else: ?><span class="btn secondary is-disabled" aria-disabled="true">Suivante →</span><?php endif; ?>
  </div>
</nav>
<p class="footer">Les restrictions de profil sont appliquées côté serveur, même si un lien ou un identifiant est modifié dans l’adresse.</p>
</main><footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer><script src="assets/idle-timeout.js" defer></script><script src="assets/toolbar-fit.js" defer></script></body></html>
