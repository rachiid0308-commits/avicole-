<?php
declare(strict_types=1);

/** Randomly allocate a total while guaranteeing at least one record per province. */
function demo_distribute_farm_counts(array $provinceIds, int $farmTotal): array
{
    $ids = array_values(array_unique(array_map('intval', $provinceIds)));
    if (!$ids || count($ids) !== count($provinceIds)) throw new InvalidArgumentException('La liste des provinces est vide ou contient des identifiants répétés.');
    if ($farmTotal < count($ids)) throw new InvalidArgumentException('Il faut au moins une ferme par province.');
    $counts = array_fill_keys($ids, 1);
    for ($remaining = count($ids); $remaining < $farmTotal; $remaining++) {
        $provinceId = $ids[random_int(0, count($ids) - 1)];
        $counts[$provinceId]++;
    }
    return $counts;
}

/** Prepare Polygon/MultiPolygon components for synthetic demo-point sampling. */
function demo_prepare_geometry(array $geometry): array
{
    $type = $geometry['type'] ?? '';
    $polygons = match ($type) {
        'Polygon' => [$geometry['coordinates'] ?? []],
        'MultiPolygon' => $geometry['coordinates'] ?? [],
        default => throw new InvalidArgumentException('Géométrie administrative invalide : ' . (string)$type),
    };
    $components = [];
    foreach ($polygons as $rings) {
        if (!is_array($rings) || !isset($rings[0]) || count($rings[0]) < 4) continue;
        $outer = $rings[0];
        $longitudes = array_map(static fn(array $point): float => (float)$point[0], $outer);
        $latitudes = array_map(static fn(array $point): float => (float)$point[1], $outer);
        $area = 0.0;
        $count = count($outer);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $area += ((float)$outer[$j][0] * (float)$outer[$i][1]) - ((float)$outer[$i][0] * (float)$outer[$j][1]);
        }
        $area = abs($area) / 2;
        if ($area <= 0) continue;
        $components[] = [
            'rings' => $rings,
            'min_lon' => min($longitudes), 'max_lon' => max($longitudes),
            'min_lat' => min($latitudes), 'max_lat' => max($latitudes),
            'weight' => $area,
        ];
    }
    if (!$components) throw new InvalidArgumentException('La province ne contient aucun polygone exploitable.');
    return $components;
}

function demo_point_in_ring(float $longitude, float $latitude, array $ring): bool
{
    $inside = false;
    $count = count($ring);
    if ($count < 4) return false;
    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
        $xi = (float)$ring[$i][0]; $yi = (float)$ring[$i][1];
        $xj = (float)$ring[$j][0]; $yj = (float)$ring[$j][1];
        if (($yi > $latitude) !== ($yj > $latitude)) {
            $crossingLongitude = ($xj - $xi) * ($latitude - $yi) / ($yj - $yi) + $xi;
            if ($longitude < $crossingLongitude) $inside = !$inside;
        }
    }
    return $inside;
}

function demo_geometry_contains(float $longitude, float $latitude, array $components): bool
{
    foreach ($components as $component) {
        if ($longitude < $component['min_lon'] || $longitude > $component['max_lon'] || $latitude < $component['min_lat'] || $latitude > $component['max_lat']) continue;
        $rings = $component['rings'];
        if (!demo_point_in_ring($longitude, $latitude, $rings[0])) continue;
        $inHole = false;
        for ($i = 1, $ringCount = count($rings); $i < $ringCount; $i++) {
            if (demo_point_in_ring($longitude, $latitude, $rings[$i])) { $inHole = true; break; }
        }
        if (!$inHole) return true;
    }
    return false;
}

/** Return [latitude, longitude] sampled within the supplied administrative polygon. */
function demo_sample_point(array $components): array
{
    $totalWeight = array_sum(array_column($components, 'weight'));
    if ($totalWeight <= 0) throw new InvalidArgumentException('Aucune surface de province exploitable.');
    for ($attempt = 0; $attempt < 20000; $attempt++) {
        $threshold = (random_int(0, 1000000000) / 1000000000) * $totalWeight;
        $cumulative = 0.0;
        $selected = $components[array_key_last($components)];
        foreach ($components as $component) {
            $cumulative += $component['weight'];
            if ($threshold <= $cumulative) { $selected = $component; break; }
        }
        $longitude = $selected['min_lon'] + (random_int(0, 1000000000) / 1000000000) * ($selected['max_lon'] - $selected['min_lon']);
        $latitude = $selected['min_lat'] + (random_int(0, 1000000000) / 1000000000) * ($selected['max_lat'] - $selected['min_lat']);
        if (demo_geometry_contains($longitude, $latitude, [$selected])) return [$latitude, $longitude];
    }
    throw new RuntimeException('Échantillonnage impossible dans une province après 20 000 essais.');
}
