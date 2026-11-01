<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
$scopedRows = list_farms($user, [], 'region', 'province');
$farmsById = [];
foreach ($scopedRows as $farm) {
    $id = (int)$farm['id'];
    $farmsById[$id] = [
        'id' => $id, 'name' => (string)$farm['farm_name'], 'province' => (string)$farm['province_name'],
        'region' => (string)$farm['region_name'], 'province_id' => (int)$farm['province_id'], 'region_id' => (int)$farm['region_id'],
        'type' => (string)$farm['farm_type'], 'lat' => (float)$farm['latitude'], 'lon' => (float)$farm['longitude'], 'visible' => true,
    ];
}
$visibleFarms = array_values($farmsById);
if ($visibleFarms && !has_global_scope($user)) {
    $latitudes = array_column($visibleFarms, 'lat');
    $longitudes = array_column($visibleFarms, 'lon');
    $latPad = 3.0 / 110.5;
    $maxAbsLat = max(array_map(static fn(float $lat): float => abs($lat), $latitudes));
    $lonPad = 3.0 / max(111.32 * cos(deg2rad(min($maxAbsLat, 89.0))), 1.0);
    $bounds = [
        ':min_lat' => min($latitudes) - $latPad, ':max_lat' => max($latitudes) + $latPad,
        ':min_lon' => min($longitudes) - $lonPad, ':max_lon' => max($longitudes) + $lonPad,
    ];
    $nearby = db()->prepare('SELECT f.id, f.farm_type, f.latitude, f.longitude, f.region_id, f.province_id, r.name AS region_name, p.name AS province_name FROM farms f JOIN regions r ON r.id=f.region_id JOIN provinces p ON p.id=f.province_id WHERE f.latitude BETWEEN :min_lat AND :max_lat AND f.longitude BETWEEN :min_lon AND :max_lon');
    $nearby->execute($bounds);
    foreach ($nearby->fetchAll() as $farm) {
        $id = (int)$farm['id'];
        if (isset($farmsById[$id])) continue;
        $farmsById[$id] = [
            'id' => $id, 'name' => '', 'province' => (string)$farm['province_name'],
            'region' => (string)$farm['region_name'], 'province_id' => (int)$farm['province_id'], 'region_id' => (int)$farm['region_id'],
            'type' => (string)$farm['farm_type'], 'lat' => (float)$farm['latitude'], 'lon' => (float)$farm['longitude'], 'visible' => false,
        ];
    }
}
$allFarms = array_values($farmsById);
$violatingUnits = [];
$allCount = count($allFarms);
if ($allCount > 1) {
    // Grille spatiale : évite une comparaison O(n²) qui dépasse le délai d’exécution
    // PHP avec le jeu national (jusqu’à 6 000 unités). La plus grande distance
    // réglementaire contrôlée est de 3 km ; une cellule de 2 km et un voisinage de
    // ±2 cellules couvrent toute paire concernée.
    $cellKm = 2.0;
    $kmPerDegree = 111.32;
    $cosReference = cos(deg2rad(31));
    $cellCoordinate = static function (array $farm) use ($cellKm, $kmPerDegree, $cosReference): array {
        return [
            (int)floor($farm['lon'] * $kmPerDegree * $cosReference / $cellKm),
            (int)floor($farm['lat'] * $kmPerDegree / $cellKm),
        ];
    };
    $grid = [];
    foreach ($allFarms as $index => $farm) {
        [$cellX, $cellY] = $cellCoordinate($farm);
        $grid[$cellX . ':' . $cellY][] = $index;
    }
    for ($i = 0; $i < $allCount; $i++) {
        $farmA = $allFarms[$i];
        [$cellX, $cellY] = $cellCoordinate($farmA);
        for ($dx = -2; $dx <= 2; $dx++) {
            for ($dy = -2; $dy <= 2; $dy++) {
                $bucket = $grid[($cellX + $dx) . ':' . ($cellY + $dy)] ?? null;
                if ($bucket === null) continue;
                foreach ($bucket as $j) {
                    if ($j <= $i) continue;
                    $farmB = $allFarms[$j];
                    if (!$farmA['visible'] && !$farmB['visible']) continue;
                    $minimum = minimum_farm_distance_km($farmA['type'], $farmB['type']);
                    $distance = haversine_distance_km($farmA['lat'], $farmA['lon'], $farmB['lat'], $farmB['lon']);
                    if ($distance >= $minimum) continue;
                    foreach ([[$farmA, $farmB], [$farmB, $farmA]] as [$unit, $nearbyUnit]) {
                        if (!$unit['visible']) continue;
                        $ratio = $distance / $minimum;
                        if (!isset($violatingUnits[$unit['id']]) || $ratio < $violatingUnits[$unit['id']]['ratio']) {
                            $violatingUnits[$unit['id']] = ['unit' => $unit, 'nearby' => $nearbyUnit, 'distance' => $distance, 'minimum' => $minimum, 'ratio' => $ratio];
                        }
                    }
                }
            }
        }
    }
}
$violations = array_values($violatingUnits);
usort($violations, static fn(array $a, array $b): int => $a['ratio'] <=> $b['ratio']);
$farmsJson = json_encode(array_map(static fn(array $farm): array => [
    'id' => $farm['id'], 'name' => $farm['name'], 'province' => $farm['province'], 'type' => $farm['type'], 'lat' => $farm['lat'], 'lon' => $farm['lon'],
], $visibleFarms), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light">
  <title>Distances — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title>
  <link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="stylesheet" href="assets/distance-alerts.css">
<link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head>
<body>
<header class="site-header">
  <a class="brand" href="index.php"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><span><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></span></a>
  <?php require __DIR__ . '/navigation.php'; ?>
</header>
<main>
  <section class="card legal-card">
    <h1>Calcul des distances réglementaires</h1>
    <p class="legal-reference"><strong>Référence :</strong> arrêté du ministre de l’Agriculture n° 2129-05 du 15 décembre 2005. Les distances sont calculées à vol d’oiseau à partir des coordonnées GPS enregistrées.</p>
    <div class="distance-rules" aria-label="Distances minimales réglementaires">
      <article><b>3 km</b><span>Élevage de reproducteurs ↔ toute autre unité avicole, y compris un couvoir.</span></article>
      <article><b>2 km</b><span>Couvoir ↔ couvoir ou autre élevage hors reproducteurs.</span></article>
      <article><b>2 km</b><span>Pondeuses ↔ pondeuses ou élevage de volailles de chair (poulet / dinde).</span></article>
      <article><b>1 km</b><span>Deux élevages de volailles de chair (poulet de chair / dinde).</span></article>
    </div>
    <h2>Comparer deux unités enregistrées</h2>
    <div class="compare-controls">
      <label>Unité A<select id="farm-a" aria-label="Première unité"></select></label>
      <span class="compare-arrow" aria-hidden="true">↔</span>
      <label>Unité B<select id="farm-b" aria-label="Deuxième unité"></select></label>
    </div>
    <div id="comparison" class="comparison-result" aria-live="polite">Sélectionnez deux unités.</div>
  </section>
  <section class="card">
    <div class="page-heading"><div><h2>Unités sous la distance minimale</h2><p class="help">Sont signalées uniquement les unités de votre périmètre qui dépassent le seuil réglementaire, y compris face à une unité proche située dans une province ou préfecture limitrophe. Les détails des unités hors périmètre restent masqués.</p></div><span class="count-pill"><?=count($violations)?> unité(s) en alerte</span></div>
    <?php if (!$violations): ?><p class="empty-state">Aucune unité de votre périmètre ne se trouve sous le minimum réglementaire, y compris à proximité des limites provinciales.</p>
    <?php else: ?><div class="responsive-table"><table><thead><tr><th>Unité signalée</th><th>Type</th><th>Unité proche</th><th>Distance</th><th>Minimum requis</th></tr></thead><tbody>
      <?php foreach (array_slice($violations, 0, 100) as $alert): $unit=$alert['unit']; $nearbyUnit=$alert['nearby']; ?>
        <tr><td><?php if (has_province_scope($user)): ?><span class="distance-alert-unit"><span class="distance-alert-ring" aria-hidden="true">!</span><strong><?=e($unit['name'])?></strong><span class="distance-alert-arrow" aria-hidden="true">➜</span></span><?php else: ?><strong><?=e($unit['name'])?></strong><?php endif; ?><small><?=e($unit['province'])?></small></td><td><?=e($unit['type'])?></td><td><?php if ($nearbyUnit['visible']): ?><?=e($nearbyUnit['name'])?> <small><?=e($nearbyUnit['province'])?></small><?php else: ?><span class="help">Unité voisine hors de votre périmètre</span><?php endif; ?><small><?=e($nearbyUnit['type'])?></small></td><td class="danger-text"><?=number_format($alert['distance'], 2, ',', ' ')?> km</td><td><?=number_format($alert['minimum'], 0, ',', ' ')?> km</td></tr>
      <?php endforeach; ?>
    </tbody></table></div><?php if (count($violations) > 100): ?><p class="help">Les 100 unités les plus proches du seuil sont affichées sur <?=count($violations)?>.</p><?php endif; ?><?php endif; ?>
  </section>
  <p class="help">Résultat indicatif selon les coordonnées disponibles; vérifiez les données et les exigences administratives applicables avant toute décision.</p>
</main>
<footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer>
<script>
'use strict';
const farms = <?=$farmsJson?>;
const minimum=(a,b)=>a==='Reproducteurs'||b==='Reproducteurs'?3:(a==='Couvoir'||b==='Couvoir'||a==='Pondeuses'||b==='Pondeuses'?2:1);
const haversine=(a,b)=>{const r=Math.PI/180,dl=(b.lat-a.lat)*r,dn=(b.lon-a.lon)*r,x=Math.sin(dl/2)**2+Math.cos(a.lat*r)*Math.cos(b.lat*r)*Math.sin(dn/2)**2;return 6371.0088*2*Math.atan2(Math.sqrt(x),Math.sqrt(1-x));};
const a=document.getElementById('farm-a'),b=document.getElementById('farm-b'),result=document.getElementById('comparison');
const option=(farm)=>{const o=document.createElement('option');o.value=farm.id;o.textContent=`${farm.name} — ${farm.province} (${farm.type})`;return o;};
farms.forEach(f=>{a.append(option(f));b.append(option(f));});
if(farms.length>1)b.selectedIndex=1;
function update(){const fa=farms.find(f=>String(f.id)===a.value),fb=farms.find(f=>String(f.id)===b.value);if(!fa||!fb){result.textContent='Ajoutez au moins deux unités avec des coordonnées.';return;}if(fa.id===fb.id){result.textContent='Choisissez deux unités différentes.';return;}const d=haversine(fa,fb),m=minimum(fa.type,fb.type),ok=d>=m;result.className='comparison-result '+(ok?'is-ok':'is-danger');result.textContent=`${d.toFixed(2)} km — minimum requis : ${m} km. ${ok?'Distance respectée.':'Distance inférieure au minimum.'}`;}
a.addEventListener('change',update);b.addEventListener('change',update);update();
</script>
<script src="assets/idle-timeout.js" defer></script>
</body></html>
