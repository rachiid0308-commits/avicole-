<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
require_once __DIR__ . '/controls_domain.php';
require_control_writer($user);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Méthode non autorisée.'); }
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) { http_response_code(413); exit('Envoi trop volumineux. Réduisez la taille des photos et vérifiez que post_max_size est réglé à au moins 8M.'); }
verify_csrf();
$reportId = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT);
$existing = null;
if ($reportId !== false && $reportId !== null && $reportId > 0) {
    [$scope, $scopeParams] = scope_clause($user, 'cr');
    $stmt = db()->prepare("SELECT cr.* FROM control_reports cr WHERE cr.id=:id AND {$scope} LIMIT 1");
    $stmt->execute([':id' => $reportId] + $scopeParams); $existing = $stmt->fetch();
    if (!$existing) { http_response_code(404); exit('Rapport introuvable ou non autorisé.'); }
}
$regionId = $existing ? (int)$existing['region_id'] : (int)(filter_var($_POST['region_id'] ?? null, FILTER_VALIDATE_INT) ?: 0);
$provinceId = $existing ? (int)$existing['province_id'] : (int)(filter_var($_POST['province_id'] ?? null, FILTER_VALIDATE_INT) ?: 0);
$type = $existing ? (string)$existing['inspection_type'] : (string)($_POST['inspection_type'] ?? '');
$template = control_checklists()[$type] ?? null;
$geoChoice = null;
foreach (geography_rows($user)['rows'] as $row) if ((int)$row['region_id'] === $regionId && (int)$row['province_id'] === $provinceId) { $geoChoice = $row; break; }
if (!$template || !$geoChoice) { http_response_code(400); exit('Type de contrôle ou province invalide pour votre profil.'); }
$metadataInput = $_POST['metadata'] ?? []; if (!is_array($metadataInput)) $metadataInput = [];
$inspectionDate = trim((string)($metadataInput['inspection_date'] ?? ''));
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $inspectionDate);
if (!$date || $date->format('Y-m-d') !== $inspectionDate) { flash('La date du contrôle est obligatoire et doit être valide.', 'error'); header('Location: control_form.php?' . http_build_query($existing ? ['id' => $reportId] : ['region_id'=>$regionId,'province_id'=>$provinceId,'type'=>$type])); exit; }
if ($type === 'transport') {
    $authorizationDate = trim((string)($metadataInput['authorization_date'] ?? ''));
    $authorizationDateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $authorizationDate);
    if (!$authorizationDateValue || $authorizationDateValue->format('Y-m-d') !== $authorizationDate) {
        flash('La date d’autorisation est obligatoire et doit être valide.', 'error');
        header('Location: control_form.php?' . http_build_query($existing ? ['id' => $reportId] : ['region_id'=>$regionId,'province_id'=>$provinceId,'type'=>$type])); exit;
    }
}
$metadata = [];
foreach ($template['fields'] as $key => $_field) {
    if (in_array($key, ['inspection_date','commission_label','unit_name','authorization_number','society_name','owner_name'], true)) continue;
    $value = trim((string)($metadataInput[$key] ?? ''));
    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > 500) { flash('Un champ dépasse la limite de 500 caractères.', 'error'); header('Location: control_form.php?' . http_build_query($existing ? ['id'=>$reportId] : ['region_id'=>$regionId,'province_id'=>$provinceId,'type'=>$type])); exit; }
    $metadata[$key] = $value;
}
foreach ($template['questions'] as $key => $_label) $metadata[$key] = trim((string)($metadataInput[$key] ?? ''));
$commission = trim((string)($metadataInput['commission_label'] ?? ''));
$authorizationNumber = '';
$ownerName = '';
$farm = null;
if ($type === 'transport') {
    $serial = trim((string)($metadataInput['registration_serial'] ?? ''));
    $letter = trim((string)($metadataInput['registration_letter'] ?? ''));
    $code = trim((string)($metadataInput['registration_code'] ?? ''));
    if (!valid_moroccan_registration($serial, $letter, $code)) { flash('Saisissez une immatriculation marocaine au format XXXXX-lettre-YY.', 'error'); header('Location: control_form.php?' . http_build_query($existing ? ['id'=>$reportId] : ['region_id'=>$regionId,'province_id'=>$provinceId,'type'=>$type])); exit; }
    $metadata['registration'] = $serial . '-' . $letter . '-' . $code;
    $metadata['products'] = trim((string)($metadataInput['products'] ?? ''));
    if (!in_array($metadata['products'], ['poussin','eggs','live_poultry'], true)) { flash('Choisissez le produit à transporter.', 'error'); header('Location: control_form.php?' . http_build_query($existing ? ['id'=>$reportId] : ['region_id'=>$regionId,'province_id'=>$provinceId,'type'=>$type])); exit; }
    $authorizationNumber = trim((string)($metadataInput['authorization_number'] ?? ''));
    $ownerName = trim((string)($metadataInput['owner_name'] ?? ''));
    if (function_exists('mb_strlen') && (mb_strlen($authorizationNumber,'UTF-8') > 100 || mb_strlen($ownerName,'UTF-8') > 190)) { http_response_code(400); exit('Le numéro d’autorisation ou le nom du propriétaire est trop long.'); }
} else {
    $farmId = (int)(filter_var($_POST['farm_id'] ?? null, FILTER_VALIDATE_INT) ?: 0);
    $farm = $farmId > 0 ? farm_by_id($farmId, $user) : null;
    $typeMatches = $farm && match ($type) { 'chair'=>$farm['farm_type']==='Chair','reproducteurs'=>$farm['farm_type']==='Reproducteurs','pondeuses'=>$farm['farm_type']==='Pondeuses',default=>false };
    if (!$farm || !$typeMatches || (int)$farm['region_id'] !== $regionId || (int)$farm['province_id'] !== $provinceId) { http_response_code(400); exit('Sélection d’unité invalide pour votre profil.'); }
    $ownerName = trim((string)($_POST['owner_name'] ?? $farm['owner_name'] ?? ''));
    $allowedOwners = [];
    foreach (list_farms($user, ['region_id'=>(string)$regionId,'province_id'=>(string)$provinceId], 'name', 'global') as $entry) if (trim((string)($entry['owner_name']??'')) !== '') $allowedOwners[trim((string)$entry['owner_name'])] = true;
    if ($ownerName !== '' && !isset($allowedOwners[$ownerName])) { http_response_code(400); exit('Le propriétaire sélectionné ne figure pas dans la liste autorisée.'); }
    $authorizationNumber = (string)($farm['authorization_number'] ?? '');
}
$controllers = $_POST['controllers'] ?? [];
if (!is_array($controllers) || count($controllers) > 12) { http_response_code(400); exit('La commission peut contenir au maximum 12 contrôleurs.'); }
$conclusion = trim((string)($_POST['conclusion'] ?? ''));
if ($conclusion === '' || (function_exists('mb_strlen') && mb_strlen($conclusion, 'UTF-8') > 8000)) { flash('La conclusion de la commission est obligatoire (8 000 caractères maximum).', 'error'); header('Location: control_form.php?' . http_build_query($existing ? ['id'=>$reportId] : ['region_id'=>$regionId,'province_id'=>$provinceId,'type'=>$type])); exit; }
$report = [
    'region_id'=>$regionId,'province_id'=>$provinceId,'farm_id'=>$farm ? (int)$farm['id'] : null,'type'=>$type,
    'inspection_date'=>$inspectionDate,'commission_label'=>$commission,'unit_name'=>$farm ? $farm['farm_name'] : '',
    'authorization_number'=>$authorizationNumber,'owner_name'=>$ownerName,'metadata'=>$metadata,'conclusion'=>$conclusion,'created_by'=>(int)$user['id'],
];
try {
    $vehicleUpload=$_FILES['vehicle_photos']??[]; $unitUpload=$_FILES['unit_photos']??[];
    $vehiclePhotos=control_vehicle_photo_uploads(is_array($vehicleUpload)?$vehicleUpload:[]);
    $unitPhotos=control_unit_photo_uploads(is_array($unitUpload)?$unitUpload:[]);
    $deleteVehicleViews=vehicle_photo_deletions($_POST['delete_vehicle_photos']??[]);
    $deleteUnitSlots=photo_slot_deletions($_POST['delete_unit_photos']??[]);
    if ($type==='transport' && ($unitPhotos||$deleteUnitSlots)) throw new InvalidArgumentException('Les photos de visite d’unité sont réservées aux contrôles d’unité.');
    if ($type!=='transport' && ($vehiclePhotos||$deleteVehicleViews)) throw new InvalidArgumentException('Les photos de véhicule sont réservées aux contrôles de transport.');
    if ($existing) { control_update(db(),(int)$reportId,$report,$_POST['answers']??[],$controllers,$vehiclePhotos,$unitPhotos,$deleteVehicleViews,$deleteUnitSlots); $id=(int)$reportId; }
    else $id=control_create(db(),$report,$_POST['answers']??[],$controllers,$vehiclePhotos,$unitPhotos);
    header('Location: control_report.php?id=' . $id); exit;
} catch (Throwable $exception) {
    error_log('Enregistrement du contrôle : ' . $exception->getMessage());
    flash($exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Le rapport n’a pas pu être enregistré. Vérifiez le schéma SQL du module Contrôles.', 'error');
    header('Location: control_form.php?' . http_build_query($existing ? ['id'=>$reportId] : ['region_id'=>$regionId,'province_id'=>$provinceId,'type'=>$type])); exit;
}
