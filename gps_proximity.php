<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    exit;
};
$manualCoordinates = array_key_exists('latitude', $_GET) || array_key_exists('longitude', $_GET) || array_key_exists('farm_type', $_GET);
$visibleRows = list_farms($user, [], 'region', 'province');
$visibleById = [];
foreach ($visibleRows as $row) $visibleById[(int)$row['id']] = $row;

if ($manualCoordinates) {
    if (!has_province_scope($user)) $respond(403, ['error' => 'La localisation par coordonnées saisies est réservée au Chef de service et au vétérinaire.']);
    $rawLatitude = $_GET['latitude'] ?? null;
    $rawLongitude = $_GET['longitude'] ?? null;
    $targetType = $_GET['farm_type'] ?? null;
    if (!is_scalar($rawLatitude) || !is_scalar($rawLongitude) || !is_string($targetType) || !in_array($targetType, FARM_TYPES, true)) {
        $respond(400, ['error' => 'Saisissez une latitude, une longitude et un type d’unité valides.']);
    }
    $latitude = filter_var((string)$rawLatitude, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var((string)$rawLongitude, FILTER_VALIDATE_FLOAT);
    if ($latitude === false || $longitude === false || !is_finite((float)$latitude) || !is_finite((float)$longitude) || abs((float)$latitude) > 90 || abs((float)$longitude) > 180) {
        $respond(400, ['error' => 'Les coordonnées doivent respecter les limites : latitude −90 à 90, longitude −180 à 180.']);
    }
    $latitude = (float)$latitude;
    $longitude = (float)$longitude;
    $target = ['id'=>null,'farm_name'=>'Emplacement saisi','province_name'=>'','region_name'=>'','farm_type'=>$targetType,'latitude'=>$latitude,'longitude'=>$longitude];
} else {
    $targetId = filter_var($_GET['farm_id'] ?? null, FILTER_VALIDATE_INT);
    if ($targetId === false || $targetId === null || $targetId < 1) $respond(400, ['error' => 'Choisissez une unité valide.']);
    $target = $visibleById[(int)$targetId] ?? null;
    if (!$target) $respond(404, ['error' => 'Unité introuvable ou hors de votre périmètre.']);
    $latitude = (float)($target['latitude'] ?? NAN);
    $longitude = (float)($target['longitude'] ?? NAN);
    if (!is_finite($latitude) || !is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
        $respond(422, ['error' => 'Cette unité ne possède pas de coordonnées GPS valides.']);
    }
    $targetType = (string)$target['farm_type'];
}


$radiusKm = 3.0;
$latPad = $radiusKm / 110.5;
$lonPad = $radiusKm / max(111.32 * cos(deg2rad(min(abs($latitude), 89.0))), 1.0);
$stmt = db()->prepare('SELECT id,farm_type,latitude,longitude,region_id,province_id FROM farms WHERE latitude BETWEEN :min_lat AND :max_lat AND longitude BETWEEN :min_lon AND :max_lon');
$stmt->execute([
    ':min_lat' => $latitude - $latPad,
    ':max_lat' => $latitude + $latPad,
    ':min_lon' => $longitude - $lonPad,
    ':max_lon' => $longitude + $lonPad,
]);
$neighbors = [];
foreach ($stmt->fetchAll() as $candidate) {
    $candidateId = (int)$candidate['id'];
    if ($target['id'] !== null && $candidateId === (int)$target['id']) continue;
    $candidateLatitude = (float)$candidate['latitude'];
    $candidateLongitude = (float)$candidate['longitude'];
    if (!is_finite($candidateLatitude) || !is_finite($candidateLongitude) || abs($candidateLatitude) > 90 || abs($candidateLongitude) > 180) continue;
    $minimum = minimum_farm_distance_km($targetType, (string)$candidate['farm_type']);
    $distance = haversine_distance_km($latitude, $longitude, $candidateLatitude, $candidateLongitude);
    if ($distance >= $minimum) continue;
    $isVisible = isset($visibleById[$candidateId]);
    $visibleCandidate = $isVisible ? $visibleById[$candidateId] : null;
    $neighbor = [
        'visible' => $isVisible,
        'lat' => $candidateLatitude,
        'lon' => $candidateLongitude,
        'distance' => round($distance, 3),
        'minimum' => $minimum,
    ];
    if ($visibleCandidate !== null) {
        $neighbor['name'] = (string)$visibleCandidate['farm_name'];
        $neighbor['province'] = (string)$visibleCandidate['province_name'];
        $neighbor['region'] = (string)$visibleCandidate['region_name'];
        $neighbor['type'] = (string)$visibleCandidate['farm_type'];
    }
    $neighbors[] = $neighbor;
}
usort($neighbors, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
$respond(200, [
    'target' => [
        'id' => $target['id'] === null ? null : (int)$target['id'],
        'name' => (string)$target['farm_name'],
        'province' => (string)$target['province_name'],
        'region' => (string)$target['region_name'],
        'type' => (string)$target['farm_type'],
        'lat' => $latitude,
        'lon' => $longitude,
    ],
    'neighbors' => $neighbors,
]);
