<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
if (!can_manage_farm($user)) { http_response_code(403); exit('Profil en consultation seule.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Méthode non autorisée.'); }
verify_csrf();
$id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
if (!$id && !can_create_farm($user)) { http_response_code(403); exit('Vous ne pouvez pas ajouter une unité.'); }
try {
    $existing = $id ? farm_by_id($id, $user) : null;
    if ($id && !$existing) { http_response_code(404); exit('Unité introuvable ou non autorisée.'); }
    $regionId = has_global_scope($user) ? (int)($_POST['region_id'] ?? 0) : (int)$user['region_id'];
    $provinceId = has_province_scope($user) ? (int)$user['province_id'] : (int)($_POST['province_id'] ?? 0);
    $geo = db()->prepare('SELECT id,region_id FROM provinces WHERE id=:id');
    $geo->execute([':id' => $provinceId]);
    $province = $geo->fetch();
    if (!$regionId || !$province || (int)$province['region_id'] !== $regionId) throw new InvalidArgumentException('Région et province incompatibles.');
    $scopeProbe = ['role'=>$user['role'],'region_id'=>$user['region_id'],'province_id'=>$user['province_id']];
    if (!can_access_farm($scopeProbe, ['region_id'=>$regionId,'province_id'=>$provinceId])) throw new InvalidArgumentException('Cette province ne fait pas partie de votre périmètre.');
    $status = (string)($_POST['authorization_status'] ?? '');
    $type = (string)($_POST['farm_type'] ?? '');
    $name = trim((string)($_POST['farm_name'] ?? ''));
    $lat = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $lon = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    if (!in_array($status, FARM_STATUSES, true)) throw new InvalidArgumentException('Statut invalide.');
    if (!in_array($type, FARM_TYPES, true)) throw new InvalidArgumentException('Type d’unité invalide.');
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($name === '' || $nameLength > 190) throw new InvalidArgumentException('Le nom de l’unité est obligatoire (190 caractères maximum).');
    if ($lat === false || $lon === false || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) throw new InvalidArgumentException('Coordonnées géographiques invalides.');
    $agreement = trim((string)($_POST['agreement_number'] ?? ''));
    $authorization = trim((string)($_POST['authorization_number'] ?? ''));
    if ($status === 'Accord de principe' && $agreement === '') throw new InvalidArgumentException('Le numéro d’accord est obligatoire pour un accord de principe.');
    if ($status === 'Accord refusé') $authorization = '';
    $date = static function (string $key): ?string {
        $value = trim((string)($_POST[$key] ?? ''));
        if ($value === '') return null;
        $parsed = DateTime::createFromFormat('!Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) throw new InvalidArgumentException('Une date est invalide.');
        return $value;
    };
    $values = [
        ':region_id'=>$regionId, ':province_id'=>$provinceId, ':authorization_status'=>$status,
        ':agreement_number'=>$agreement ?: null, ':authorization_number'=>$authorization ?: null,
        ':owner_name'=>trim((string)($_POST['owner_name'] ?? '')) ?: null,
        ':national_id'=>trim((string)($_POST['national_id'] ?? '')) ?: null,
        ':farm_name'=>$name, ':latitude'=>$lat, ':longitude'=>$lon, ':farm_type'=>$type,
        ':first_agreement_date'=>$date('first_agreement_date'), ':second_agreement_date'=>$date('second_agreement_date'),
        ':construction_deadline'=>$date('construction_deadline'), ':user_id'=>(int)$user['id'],
    ];
    if ($existing) {
        db()->prepare('UPDATE farms SET region_id=:region_id,province_id=:province_id,authorization_status=:authorization_status,agreement_number=:agreement_number,authorization_number=:authorization_number,owner_name=:owner_name,national_id=:national_id,farm_name=:farm_name,latitude=:latitude,longitude=:longitude,farm_type=:farm_type,first_agreement_date=:first_agreement_date,second_agreement_date=:second_agreement_date,construction_deadline=:construction_deadline,updated_by=:user_id WHERE id=:id')
            ->execute($values + [':id'=>$id]);
        flash('Unité avicole modifiée.');
    } else {
        $insertValues = $values;
        unset($insertValues[':user_id']);
        db()->prepare('INSERT INTO farms (region_id,province_id,authorization_status,agreement_number,authorization_number,owner_name,national_id,farm_name,latitude,longitude,farm_type,first_agreement_date,second_agreement_date,construction_deadline,created_by,updated_by) VALUES (:region_id,:province_id,:authorization_status,:agreement_number,:authorization_number,:owner_name,:national_id,:farm_name,:latitude,:longitude,:farm_type,:first_agreement_date,:second_agreement_date,:construction_deadline,:created_by,:updated_by)')
            ->execute($insertValues + [':created_by'=>(int)$user['id'], ':updated_by'=>(int)$user['id']]);
        flash('Unité avicole ajoutée.');
    }
    header('Location: units.php'); exit;
} catch (InvalidArgumentException $ex) {
    flash($ex->getMessage(), 'error');
    header('Location: ' . ($id ? 'farm_form.php?id=' . $id : 'farm_form.php')); exit;
} catch (Throwable $ex) {
    error_log((string)$ex);
    flash('Enregistrement impossible. Vérifiez les données ou contactez l’administrateur.', 'error');
    header('Location: ' . ($id ? 'farm_form.php?id=' . $id : 'farm_form.php')); exit;
}
