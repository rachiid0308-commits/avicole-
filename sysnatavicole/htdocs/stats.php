<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
require_once __DIR__ . '/controls_domain.php';
$rows = list_farms($user, [], 'region', 'province');
$role = (string)$user['role'];
[$vehicleScope, $vehicleParams] = scope_clause($user, 'cr');
$vehicleReports = [];
$vehicleStatsUnavailable = false;
try {
    $vehicleStmt = db()->prepare("SELECT cr.metadata_json, cr.inspection_date, r.name AS region_name, p.name AS province_name FROM control_reports cr JOIN regions r ON r.id=cr.region_id JOIN provinces p ON p.id=cr.province_id WHERE {$vehicleScope} AND cr.inspection_type='transport' ORDER BY cr.inspection_date DESC, cr.id DESC");
    $vehicleStmt->execute($vehicleParams);
    $vehicleReports = $vehicleStmt->fetchAll();
} catch (PDOException $exception) {
    $message = strtolower($exception->getMessage());
    $tableMissing = str_contains($message, 'control_reports')
        && (str_contains($message, 'no such table') || str_contains($message, "doesn't exist") || str_contains($message, 'does not exist'));
    if (!$tableMissing) throw $exception;
    $vehicleStatsUnavailable = true;
}
$vehicleReports = array_values(array_filter($vehicleReports, static fn(array $report): bool => !transport_authorization_status($report)['expired']));
$vehicleTotal = count($vehicleReports);
$vehicleByProduct = $vehicleByGeography = [];
$productLabels = ['poussin'=>'Poussins', 'eggs'=>'Œufs à couver (OAC)', 'live_poultry'=>'Volailles vivantes'];
foreach ($vehicleReports as $vehicleReport) {
    $meta = json_decode((string)($vehicleReport['metadata_json'] ?? ''), true);
    $meta = is_array($meta) ? $meta : [];
    $product = $productLabels[(string)($meta['products'] ?? '')] ?? 'Non renseigné';
    $region = trim((string)($vehicleReport['region_name'] ?? '')) ?: 'Région non renseignée';
    $province = trim((string)($vehicleReport['province_name'] ?? '')) ?: 'Province non renseignée';
    $geography = $region . ' — ' . $province;
    $vehicleByProduct[$product] = ($vehicleByProduct[$product] ?? 0) + 1;
    $vehicleByGeography[$geography] = ($vehicleByGeography[$geography] ?? 0) + 1;
}
foreach ([$vehicleByProduct, $vehicleByGeography] as &$vehicleCounts) ksort($vehicleCounts, SORT_NATURAL | SORT_FLAG_CASE);
unset($vehicleCounts);
$territorialTables = [];
if (has_global_scope($user)) {
    $scopeTitle = 'Statistiques nationales — toutes les régions';
    $territorialTables[] = ['title'=>'Répartition par région','dimension'=>'region_name','label'=>'Région'];
    $territorialTables[] = ['title'=>'Répartition par province / préfecture','dimension'=>'province_name','label'=>'Province / préfecture'];
} elseif (has_region_scope($user)) {
    $scopeTitle = 'Région autorisée : ' . (string)($user['region_name'] ?? 'non définie');
    $territorialTables[] = ['title'=>'Répartition par province / préfecture','dimension'=>'province_name','label'=>'Province / préfecture'];
} else {
    $scopeTitle = 'Province autorisée : ' . (string)($user['province_name'] ?? 'non définie');
}
$total = count($rows);
foreach ($territorialTables as &$table) $table['counts'] = aggregate_farm_rows($rows, $table['dimension']);
unset($table);
$byStatus = aggregate_farm_rows($rows, 'authorization_status');
$byType = aggregate_farm_rows($rows, 'farm_type');
$provinceMatrix = [];
foreach ($rows as $farm) {
    $region = trim((string)($farm['region_name'] ?? '')) ?: 'Région non renseignée';
    $province = trim((string)($farm['province_name'] ?? '')) ?: 'Province non renseignée';
    $type = (string)($farm['farm_type'] ?? '');
    $status = (string)($farm['authorization_status'] ?? '');
    if (!isset($provinceMatrix[$region][$province])) {
        $provinceMatrix[$region][$province] = ['total' => 0, 'types' => [], 'statuses' => []];
    }
    $provinceMatrix[$region][$province]['total']++;
    if (in_array($type, FARM_TYPES, true)) $provinceMatrix[$region][$province]['types'][$type] = ($provinceMatrix[$region][$province]['types'][$type] ?? 0) + 1;
    if (in_array($status, FARM_STATUSES, true)) $provinceMatrix[$region][$province]['statuses'][$status] = ($provinceMatrix[$region][$province]['statuses'][$status] ?? 0) + 1;
}
ksort($provinceMatrix, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($provinceMatrix as &$provinceRows) ksort($provinceRows, SORT_NATURAL | SORT_FLAG_CASE);
unset($provinceRows);
$lateSecond = 0;
$lateConstruction = 0;
foreach ($rows as $farm) {
    $timeline = farm_timeline($farm);
    if ($timeline['second_overdue']) $lateSecond++;
    if ($timeline['construction_overdue']) $lateConstruction++;
}
function render_stat_pie(array $counts, int $total, string $label, string $totalNoun = 'unité(s)'): void
{
    $chartCounts = $counts;
    arsort($chartCounts, SORT_NUMERIC);
    if (count($chartCounts) > 8) {
        $top = array_slice($chartCounts, 0, 7, true);
        $rest = array_slice($chartCounts, 7, null, true);
        $top['Autres'] = array_sum($rest);
        $chartCounts = $top;
    }
    $chartTotal = array_sum($chartCounts);
    $palette = ['#28734f','#2d6cdf','#d89b16','#7b4db1','#e36e32','#16816b','#ca4d65','#567585'];
    ?>
    <figure class="stats-pie">
      <figcaption>Répartition — <?=e($label)?></figcaption>
      <svg class="stats-pie-svg" viewBox="0 0 200 200" role="img" aria-label="Graphique à secteurs : <?=e($label)?>">
        <title>Répartition en secteurs — <?=e($label)?></title>
        <?php if ($chartTotal <= 0): ?><circle cx="100" cy="100" r="82" fill="#e6ece8"/><text x="100" y="104" text-anchor="middle" class="pie-empty-label">Aucune donnée</text>
        <?php elseif (count($chartCounts) === 1): ?><circle cx="100" cy="100" r="82" fill="<?=$palette[0]?>"><title><?=e((string)array_key_first($chartCounts))?> : <?= (int)reset($chartCounts) ?> <?=e($totalNoun)?></title></circle><text x="100" y="98" text-anchor="middle" class="pie-inside-label"><?= (int)reset($chartCounts) ?></text><text x="100" y="113" text-anchor="middle" class="pie-inside-percent">100 %</text>
        <?php else: $angle = -M_PI_2; $colorIndex = 0; foreach ($chartCounts as $name => $count): $sweep = ((int)$count / $chartTotal) * 2 * M_PI; $end = $angle + $sweep; $x1 = 100 + 82 * cos($angle); $y1 = 100 + 82 * sin($angle); $x2 = 100 + 82 * cos($end); $y2 = 100 + 82 * sin($end); $largeArc = $sweep > M_PI ? 1 : 0; $percent = $chartTotal > 0 ? ((int)$count / $chartTotal) * 100 : 0; $color = $palette[$colorIndex++ % count($palette)]; ?>
          <path d="M 100 100 L <?=number_format($x1, 3, '.', '')?> <?=number_format($y1, 3, '.', '')?> A 82 82 0 <?=$largeArc?> 1 <?=number_format($x2, 3, '.', '')?> <?=number_format($y2, 3, '.', '')?> Z" fill="<?=$color?>" stroke="#fff" stroke-width="1.5"><title><?=e((string)$name)?> : <?= (int)$count ?> (<?=e(number_format($percent, 1, ',', ' '))?> %)</title></path>
          <?php if ($percent >= 8): $middle = $angle + ($sweep / 2); $labelX = 100 + 51 * cos($middle); $labelY = 100 + 51 * sin($middle); ?><text x="<?=number_format($labelX, 2, '.', '')?>" y="<?=number_format($labelY, 2, '.', '')?>" text-anchor="middle" class="pie-inside-label"><?= (int)$count ?></text><text x="<?=number_format($labelX, 2, '.', '')?>" y="<?=number_format($labelY + 11, 2, '.', '')?>" text-anchor="middle" class="pie-inside-percent"><?=e(number_format($percent, 0, ',', ' '))?>%</text><?php endif; ?>
        <?php $angle = $end; endforeach; endif; ?>
      </svg>
      <ul class="stats-pie-legend">
        <?php $colorIndex = 0; foreach ($chartCounts as $name => $count): $color = $palette[$colorIndex++ % count($palette)]; $percent = $chartTotal > 0 ? ((int)$count / $chartTotal) * 100 : 0; ?>
          <li><span class="stats-pie-swatch" style="background:<?=$color?>"></span><span class="stats-pie-name"><?=e((string)$name)?></span><strong><?= (int)$count ?> · <?=e(number_format($percent, 1, ',', ' '))?> %</strong></li>
        <?php endforeach; ?>
      </ul>
      <?php if (count($counts) > 8): ?><p class="help stats-pie-note">Les 7 principales catégories sont détaillées; les autres sont regroupées.</p><?php endif; ?>
      <span class="stats-pie-total"><?= $total ?> <?=e($totalNoun)?></span>
    </figure>
    <?php
}
function render_stat_table(array $counts, int $total, string $label, string $measureLabel = 'Unités', string $totalNoun = 'unité(s)'): void
{
    ?>
    <div class="stats-data-visual"><div class="tablewrap stats-table-wrap"><table class="stats-table"><thead><tr><th scope="col"><?=e($label)?></th><th scope="col"><?=e($measureLabel)?></th><th scope="col">Part</th></tr></thead><tbody>
    <?php foreach ($counts as $name => $count): $percent = $total > 0 ? ((int)$count / $total) * 100 : 0; ?>
      <tr><th scope="row"><?=e($name)?></th><td><?= (int)$count ?></td><td><?=e(number_format($percent, 1, ',', ' '))?> %</td></tr>
    <?php endforeach; ?>
    </tbody><tfoot><tr><th scope="row">Total</th><td><?= $total ?></td><td><?= $total > 0 ? '100,0' : '0,0' ?> %</td></tr></tfoot></table></div><?php render_stat_pie($counts, $total, $label, $totalNoun); ?></div>
    <?php
}
function render_province_matrix(array $matrix, array $categories, string $dimension, string $title): void
{
    $bucket = $dimension === 'types' ? 'types' : 'statuses';
    ?>
    <section class="card stat-chart stat-matrix-card">
      <h2><?=e($title)?></h2>
      <p class="help">Les valeurs entre parenthèses indiquent la part du type ou du statut dans la province.</p>
      <p class="scroll-hint">↔ Faites glisser le tableau pour voir toutes les colonnes.</p>
      <div class="tablewrap stats-table-wrap stats-matrix-wrap"><table class="stats-table stats-matrix"><thead><tr><th scope="col">Province / préfecture</th><?php foreach ($categories as $category): ?><th scope="col"><?=e($category)?></th><?php endforeach; ?><th scope="col">Total province</th></tr></thead><tbody>
      <?php $grandTotal = 0; foreach ($matrix as $region => $provinces): $regionTotal = array_sum(array_column($provinces, 'total')); $grandTotal += $regionTotal; ?>
        <tr class="matrix-region"><th scope="row" colspan="<?=count($categories) + 1?>"><?=e($region)?></th><td><?= (int)$regionTotal ?></td></tr>
        <?php foreach ($provinces as $province => $values): $rowTotal = (int)$values['total']; ?>
          <tr><th scope="row" class="matrix-province"><?=e($province)?><small class="matrix-region-inline"><?=e($region)?></small></th><?php foreach ($categories as $category): $value = (int)($values[$bucket][$category] ?? 0); $share = $rowTotal > 0 ? ($value / $rowTotal) * 100 : 0; ?><td><?= $value ?><small><?=e(number_format($share, 1, ',', ' '))?>%</small></td><?php endforeach; ?><td class="matrix-total"><?= $rowTotal ?></td></tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody><tfoot><tr><th scope="row">Total visible</th><?php foreach ($categories as $category): $categoryTotal = 0; foreach ($matrix as $provinces) foreach ($provinces as $values) $categoryTotal += (int)($values[$bucket][$category] ?? 0); ?><td><?= $categoryTotal ?></td><?php endforeach; ?><td><?= $grandTotal ?></td></tr></tfoot></table></div>
    </section>
    <?php
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light"><title>Statistiques — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="stylesheet" href="assets/stats-print.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header class="site-header">
  <a class="brand" href="index.php"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><span><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></span></a>
  <?php require __DIR__ . '/navigation.php'; ?>
</header>
<main>
  <section class="card">
    <div class="page-heading"><div><h1>Statistiques selon le profil</h1><p class="help"><?=e($scopeTitle)?></p></div><span class="count-pill"><?= $total ?> unité(s)</span><?php if (($user['role'] ?? '') === ROLE_ADMINISTRATOR): ?><button class="btn secondary stats-print-action" type="button" onclick="window.print()">Imprimer / PDF</button><?php endif; ?></div>
    <div class="stats stats-overview">
      <div class="stat"><b><?= $total ?></b>Unités visibles</div>
      <div class="stat"><b><?= (int)($byStatus['Autorisé'] ?? 0) ?></b>Autorisées</div>
      <div class="stat stat-alert"><b><?= $lateSecond ?></b>2e accord en retard</div>
      <div class="stat stat-alert"><b><?= $lateConstruction ?></b>Fin des travaux en retard</div>
    </div>
    <p class="help">Les données sont limitées côté serveur à votre profil : national pour le directeur central, la direction centrale et l’administrateur; région pour le directeur régional et le chef de division; province pour le chef de service et le vétérinaire.</p>
  </section>
  <div class="stats-charts">
    <?php foreach ($territorialTables as $table): ?><section class="card stat-chart"><h2><?=e($table['title'])?></h2><?php render_stat_table($table['counts'], $total, $table['label']); ?></section><?php endforeach; ?>
    <section class="card stat-chart"><h2>Répartition par statut</h2><?php render_stat_table($byStatus, $total, 'Statut'); ?></section>
    <section class="card stat-chart"><h2>Répartition par type d’élevage</h2><?php render_stat_table($byType, $total, 'Type d’élevage'); ?></section>
  </div>
  <?php render_province_matrix($provinceMatrix, FARM_TYPES, 'types', 'Détail par région et province selon le type d’élevage'); ?>
  <?php render_province_matrix($provinceMatrix, FARM_STATUSES, 'statuses', 'Détail par région et province selon le statut'); ?>
  <section class="card stat-chart vehicle-statistics" id="vehicle-statistics">
    <h2>Statistiques des véhicules contrôlés</h2>
    <?php if ($vehicleStatsUnavailable): ?><p class="help">Les statistiques des véhicules sont indisponibles : le module Contrôles n’est pas encore installé sur cette base.</p>
    <?php else: ?>
      <p class="help">Les autorisations expirées sont exclues. Pour les anciens rapports sans date d’autorisation, la date du contrôle sert de référence.</p>
      <div class="stats stats-overview"><div class="stat"><b><?= $vehicleTotal ?></b>Contrôles actifs</div></div>
      <?php if ($vehicleTotal === 0): ?><p class="help">Aucun contrôle de véhicule actif dans votre périmètre.</p><?php else: ?>
      <div class="stats-charts">
        <section class="card stat-chart"><h3>Par produit transporté</h3><?php render_stat_table($vehicleByProduct, $vehicleTotal, 'Produit', 'Contrôles', 'contrôle(s)'); ?></section>
        <section class="card stat-chart"><h3>Par région et province</h3><?php render_stat_table($vehicleByGeography, $vehicleTotal, 'Région — province', 'Contrôles', 'contrôle(s)'); ?></section>
      </div><?php endif; ?>
    <?php endif; ?>
  </section>
</main>
<footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer>
<script src="assets/idle-timeout.js" defer></script></body></html>
