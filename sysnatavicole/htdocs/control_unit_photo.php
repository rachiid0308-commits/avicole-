<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user=require_auth();
require_once __DIR__ . '/controls_domain.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$slot=filter_input(INPUT_GET,'slot',FILTER_VALIDATE_INT);
if (!$id||$id<1||!$slot||$slot<1||$slot>6) { http_response_code(404); exit('Photo introuvable.'); }
[$scope,$params]=scope_clause($user,'cr');
$params[':id']=$id; $params[':slot']=$slot;
try {
    $stmt=db()->prepare("SELECT photo.mime_type,photo.image_data FROM control_report_unit_photos photo INNER JOIN control_reports cr ON cr.id=photo.report_id WHERE cr.id=:id AND cr.inspection_type<>'transport' AND {$scope} AND photo.photo_slot=:slot LIMIT 1");
    $stmt->execute($params); $photo=$stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $message=strtolower($exception->getMessage());
    $tableMissing=str_contains($message,'control_report_unit_photos')&&(str_contains($message,'no such table')||str_contains($message,"doesn't exist")||str_contains($message,'does not exist'));
    if ($tableMissing) { http_response_code(503); exit('Le stockage des photos de visite n’est pas installé. Importez database/profile_roles_and_unit_photos.sql.'); }
    throw $exception;
}
if (!$photo||!in_array($photo['mime_type'],['image/jpeg','image/png','image/webp'],true)) { http_response_code(404); exit('Photo introuvable ou non autorisée.'); }
$extension=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$photo['mime_type']];
$imageData=$photo['image_data']; if (is_resource($imageData)) $imageData=stream_get_contents($imageData);
if (!is_string($imageData)||$imageData==='') { http_response_code(404); exit('Photo indisponible.'); }
header('Content-Type: '.$photo['mime_type']);
header('Content-Length: '.strlen($imageData));
header('Content-Disposition: inline; filename="visite-'.$id.'-'.$slot.'.'.$extension.'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header("Content-Security-Policy: default-src 'none'; sandbox");
echo $imageData;
