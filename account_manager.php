<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

function validate_account_contact_fields(string $email, string $mobilePhone, ?string $professionalTitle): array
{
    $email=strtolower(trim($email));
    $mobilePhone=trim($mobilePhone);
    $professionalTitle=trim((string)$professionalTitle);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !str_ends_with($email,'@onssa.gov.ma')) throw new InvalidArgumentException('L’adresse e-mail doit être valide et se terminer par @onssa.gov.ma.');
    if (!preg_match('/^[0-9]{10}$/D',$mobilePhone)) throw new InvalidArgumentException('Le numéro mobile doit contenir exactement 10 chiffres.');
    if (strlen($professionalTitle)>120) throw new InvalidArgumentException('Le titre professionnel ne doit pas dépasser 120 caractères.');
    return [$email,$mobilePhone,$professionalTitle===''?null:$professionalTitle];
}

function create_profile_account(PDO $pdo, string $email, string $fullName, string $role, ?int $regionId, ?int $provinceId, string $password, string $mobilePhone='', ?string $professionalTitle=null): void
{
    [$email,$mobilePhone,$professionalTitle]=validate_account_contact_fields($email,$mobilePhone,$professionalTitle); $fullName=trim($fullName);
    if ($fullName==='' || strlen($fullName)>160) throw new InvalidArgumentException('Nom complet invalide.');
    if (strlen($password)<12) throw new InvalidArgumentException('Le mot de passe doit contenir au moins 12 caractères.');
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM users WHERE email=:email'); $stmt->execute([':email'=>$email]);
    if ((int)$stmt->fetchColumn()>0) throw new InvalidArgumentException('Cette adresse e-mail est déjà utilisée.');
    if ($role===ROLE_CENTRAL || $role===ROLE_ADMINISTRATOR || $role===ROLE_CENTRAL_VIEWER) {
        if ($regionId!==null || $provinceId!==null) throw new InvalidArgumentException('Un compte central ne reçoit pas de région ni de province.');
        $limits=[ROLE_CENTRAL=>4,ROLE_CENTRAL_VIEWER=>2];
        if (isset($limits[$role]) && (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role=".$pdo->quote($role))->fetchColumn()>=$limits[$role]) throw new InvalidArgumentException('Le nombre maximal de comptes centraux pour ce profil est atteint.');
    } elseif ($role===ROLE_REGIONAL) {
        if (!$regionId || $provinceId!==null) throw new InvalidArgumentException('Choisissez une région et laissez la province vide.');
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM regions WHERE id=:id'); $stmt->execute([':id'=>$regionId]);
        if (!(int)$stmt->fetchColumn()) throw new InvalidArgumentException('Région inconnue.');
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM users WHERE role='regional' AND region_id=:region"); $stmt->execute([':region'=>$regionId]);
        if ((int)$stmt->fetchColumn()>0) throw new InvalidArgumentException('Un compte régional existe déjà pour cette région.');
        if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='regional'")->fetchColumn()>=12) throw new InvalidArgumentException('Les 12 comptes régionaux sont déjà créés.');
    } elseif ($role===ROLE_DIVISION_HEAD) {
        if (!$regionId || $provinceId!==null) throw new InvalidArgumentException('Choisissez une région et laissez la province vide.');
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM regions WHERE id=:id'); $stmt->execute([':id'=>$regionId]);
        if (!(int)$stmt->fetchColumn()) throw new InvalidArgumentException('Région inconnue.');
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM users WHERE role=:role AND region_id=:region'); $stmt->execute([':role'=>ROLE_DIVISION_HEAD,':region'=>$regionId]);
        if ((int)$stmt->fetchColumn()>0) throw new InvalidArgumentException('Un Chef de division existe déjà pour cette région.');
    } elseif ($role===ROLE_PROVINCIAL || $role===ROLE_VETERINARY_RESPONSIBLE) {
        if (!$provinceId) throw new InvalidArgumentException('Choisissez une province ou préfecture.');
        $stmt=$pdo->prepare('SELECT region_id FROM provinces WHERE id=:id'); $stmt->execute([':id'=>$provinceId]);
        $assignedRegion=$stmt->fetchColumn();
        if (!$assignedRegion) throw new InvalidArgumentException('Province ou préfecture inconnue.');
        $assignedRegion=(int)$assignedRegion;
        if ($regionId!==null && $regionId!==$assignedRegion) throw new InvalidArgumentException('La province ne correspond pas à la région sélectionnée.');
        if ($role===ROLE_PROVINCIAL) {
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM users WHERE role='provincial' AND province_id=:province"); $stmt->execute([':province'=>$provinceId]);
            if ((int)$stmt->fetchColumn()>0) throw new InvalidArgumentException('Un compte Chef de service existe déjà pour cette province/préfecture.');
            if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='provincial'")->fetchColumn()>=75) throw new InvalidArgumentException('Les 75 comptes provinciaux sont déjà créés.');
        }
        $regionId=$assignedRegion;
    } else {
        throw new InvalidArgumentException('Profil invalide.');
    }
    $insert=$pdo->prepare('INSERT INTO users (email,password_hash,full_name,professional_title,mobile_phone,role,region_id,province_id) VALUES (:email,:hash,:name,:title,:mobile,:role,:region,:province)');
    $insert->execute([':email'=>$email,':hash'=>password_hash($password,PASSWORD_DEFAULT),':name'=>$fullName,':title'=>$professionalTitle,':mobile'=>$mobilePhone,':role'=>$role,':region'=>$regionId,':province'=>$provinceId]);
}

function account_by_id(PDO $pdo, int $id): ?array
{
    $stmt=$pdo->prepare('SELECT id,email,full_name,professional_title,mobile_phone,role,region_id,province_id,active FROM users WHERE id=:id');
    $stmt->execute([':id'=>$id]);
    $row=$stmt->fetch();
    return $row ?: null;
}

function validate_account_scope(PDO $pdo, string $role, ?int $regionId, ?int $provinceId, ?int $ignoreId = null): array
{
    $ignore=$ignoreId!==null?' AND id<>:ignore':'';
    $countWithLimit=['central'=>4,'central_viewer'=>2,'regional'=>12,'division_head'=>12,'provincial'=>75];
    if (!in_array($role,['central','administrator','central_viewer','regional','division_head','provincial','veterinary_responsible'],true)) throw new InvalidArgumentException('Profil invalide.');
    if (isset($countWithLimit[$role])) {
        $count=$pdo->prepare('SELECT COUNT(*) FROM users WHERE role=:role'.$ignore);
        $count->execute($ignoreId!==null?[':role'=>$role,':ignore'=>$ignoreId]:[':role'=>$role]);
        if ((int)$count->fetchColumn()>=$countWithLimit[$role]) throw new InvalidArgumentException('Le nombre maximal de comptes pour ce profil est atteint.');
    }
    if (in_array($role,['central','administrator','central_viewer'],true)) {
        if ($regionId!==null || $provinceId!==null) throw new InvalidArgumentException('Un compte central ne reçoit pas de région ni de province.');
        return [null,null];
    }
    if ($role===ROLE_REGIONAL || $role===ROLE_DIVISION_HEAD) {
        if (!$regionId || $provinceId!==null) throw new InvalidArgumentException('Choisissez une région et laissez la province vide.');
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM regions WHERE id=:id'); $stmt->execute([':id'=>$regionId]);
        if (!(int)$stmt->fetchColumn()) throw new InvalidArgumentException('Région inconnue.');
        if ($role===ROLE_REGIONAL || $role===ROLE_DIVISION_HEAD) {
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM users WHERE role=:role AND region_id=:region'.$ignore);
            $stmt->execute($ignoreId!==null?[':role'=>$role,':region'=>$regionId,':ignore'=>$ignoreId]:[':role'=>$role,':region'=>$regionId]);
            if ((int)$stmt->fetchColumn()>0) throw new InvalidArgumentException($role===ROLE_REGIONAL?'Un compte régional existe déjà pour cette région.':'Un Chef de division existe déjà pour cette région.');
        }
        return [$regionId,null];
    }
    if (!$provinceId) throw new InvalidArgumentException('Choisissez une province ou préfecture.');
    $stmt=$pdo->prepare('SELECT region_id FROM provinces WHERE id=:id'); $stmt->execute([':id'=>$provinceId]);
    $assignedRegion=$stmt->fetchColumn();
    if (!$assignedRegion) throw new InvalidArgumentException('Province ou préfecture inconnue.');
    $assignedRegion=(int)$assignedRegion;
    if ($regionId!==null && $regionId!==$assignedRegion) throw new InvalidArgumentException('La province ne correspond pas à la région sélectionnée.');
    if ($role===ROLE_PROVINCIAL) {
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM users WHERE role='provincial' AND province_id=:province".$ignore);
        $stmt->execute($ignoreId!==null?[':province'=>$provinceId,':ignore'=>$ignoreId]:[':province'=>$provinceId]);
        if ((int)$stmt->fetchColumn()>0) throw new InvalidArgumentException('Un Chef de service existe déjà pour cette province/préfecture.');
    }
    return [$assignedRegion,$provinceId];
}

function managed_account_roles(array $manager): array
{
    return ($manager['role'] ?? '') === ROLE_ADMINISTRATOR
        ? [ROLE_CENTRAL, ROLE_ADMINISTRATOR, ROLE_CENTRAL_VIEWER, ROLE_REGIONAL, ROLE_DIVISION_HEAD, ROLE_PROVINCIAL, ROLE_VETERINARY_RESPONSIBLE]
        : [];
}

function can_manage_account(array $manager, array $target): bool
{
    return ($manager['role'] ?? '') === ROLE_ADMINISTRATOR
        && (int)($manager['id'] ?? 0) > 0
        && (int)($target['id'] ?? 0) > 0;
}

function account_list_scope(array $manager, string $alias='u'): array
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/',$alias)) throw new InvalidArgumentException('Alias SQL invalide.');
    if (has_global_scope($manager)) return ['1=1',[]];
    if (has_region_scope($manager) && !empty($manager['region_id'])) return ["{$alias}.region_id=:account_region", [':account_region'=>(int)$manager['region_id']]];
    if (has_province_scope($manager) && !empty($manager['province_id'])) return ["{$alias}.province_id=:account_province", [':account_province'=>(int)$manager['province_id']]];
    return ['1=0',[]];
}

function create_account_for_manager(PDO $pdo, array $manager, string $email, string $fullName, string $role, ?int $regionId, ?int $provinceId, string $password, string $mobilePhone='', ?string $professionalTitle=null): void
{
    if (!in_array($role,managed_account_roles($manager),true)) throw new InvalidArgumentException('Vous ne pouvez pas créer ce profil.');
    if (in_array($manager['role'] ?? '',[ROLE_REGIONAL,ROLE_DIVISION_HEAD],true)) {
        $managerRegion=(int)($manager['region_id'] ?? 0);
        if (!$managerRegion || ($regionId!==null && $regionId!==$managerRegion)) throw new InvalidArgumentException('Vous ne pouvez gérer que les comptes de votre région.');
        $regionId=$managerRegion;
    } elseif (($manager['role'] ?? '')===ROLE_PROVINCIAL) {
        $managerProvince=(int)($manager['province_id'] ?? 0); $managerRegion=(int)($manager['region_id'] ?? 0);
        if (!$managerProvince || ($provinceId!==null && $provinceId!==$managerProvince) || ($regionId!==null && $regionId!==$managerRegion)) throw new InvalidArgumentException('Vous ne pouvez gérer que les comptes de votre province.');
        $regionId=$managerRegion; $provinceId=$managerProvince;
    }
    [$regionId,$provinceId]=validate_account_scope($pdo,$role,$regionId,$provinceId);
    create_profile_account($pdo,$email,$fullName,$role,$regionId,$provinceId,$password,$mobilePhone,$professionalTitle);
}

function update_account_for_manager(PDO $pdo, array $manager, int $id, string $email, string $fullName, string $role, ?int $regionId, ?int $provinceId, string $mobilePhone='', ?string $professionalTitle=null): void
{
    $target=account_by_id($pdo,$id);
    if (!$target || !can_manage_account($manager,$target)) throw new InvalidArgumentException('Compte introuvable ou hors de votre périmètre.');
    if (!in_array($role,managed_account_roles($manager),true) && !((int)$manager['id']===$id && in_array($manager['role'],[ROLE_CENTRAL,ROLE_ADMINISTRATOR],true))) throw new InvalidArgumentException('Vous ne pouvez pas attribuer ce profil.');
    if (in_array($manager['role'] ?? '',[ROLE_REGIONAL,ROLE_DIVISION_HEAD],true)) {
        $managerRegion=(int)($manager['region_id'] ?? 0);
        if (!$managerRegion || ($regionId!==null && $regionId!==$managerRegion)) throw new InvalidArgumentException('Le compte doit rester dans votre région.');
        $regionId=$managerRegion;
    } elseif (($manager['role'] ?? '')===ROLE_PROVINCIAL) {
        $managerProvince=(int)($manager['province_id'] ?? 0); $managerRegion=(int)($manager['region_id'] ?? 0);
        if (!$managerProvince || ($provinceId!==null && $provinceId!==$managerProvince) || ($regionId!==null && $regionId!==$managerRegion)) throw new InvalidArgumentException('Le compte doit rester dans votre province.');
        $regionId=$managerRegion; $provinceId=$managerProvince;
    }
    update_profile_account($pdo,$id,$email,$fullName,$role,$regionId,$provinceId,$mobilePhone,$professionalTitle);
}

function delete_profile_account(PDO $pdo, int $id): void
{
    $stmt=$pdo->prepare('DELETE FROM users WHERE id=:id'); $stmt->execute([':id'=>$id]);
    if ($stmt->rowCount()!==1) throw new InvalidArgumentException('Compte introuvable.');
}

function update_profile_account(PDO $pdo, int $id, string $email, string $fullName, string $role, ?int $regionId, ?int $provinceId, string $mobilePhone='', ?string $professionalTitle=null): void
{
    [$email,$mobilePhone,$professionalTitle]=validate_account_contact_fields($email,$mobilePhone,$professionalTitle); $fullName=trim($fullName);
    if (!account_by_id($pdo,$id)) throw new InvalidArgumentException('Compte introuvable.');
    if ($fullName==='' || strlen($fullName)>160) throw new InvalidArgumentException('Nom complet invalide.');
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM users WHERE email=:email AND id<>:id');
    $stmt->execute([':email'=>$email,':id'=>$id]);
    if ((int)$stmt->fetchColumn()>0) throw new InvalidArgumentException('Cette adresse e-mail est déjà utilisée.');
    [$regionId,$provinceId]=validate_account_scope($pdo,$role,$regionId,$provinceId,$id);
    $update=$pdo->prepare('UPDATE users SET email=:email,full_name=:name,professional_title=:title,mobile_phone=:mobile,role=:role,region_id=:region,province_id=:province WHERE id=:id');
    $update->execute([':email'=>$email,':name'=>$fullName,':title'=>$professionalTitle,':mobile'=>$mobilePhone,':role'=>$role,':region'=>$regionId,':province'=>$provinceId,':id'=>$id]);
}

function set_account_active(PDO $pdo, int $id, bool $active): void
{
    $stmt=$pdo->prepare('UPDATE users SET active=:active WHERE id=:id');
    $stmt->execute([':active'=>$active?1:0,':id'=>$id]);
}

function set_account_password(PDO $pdo, int $id, string $password, string $confirmation): void
{
    if (!account_by_id($pdo,$id)) throw new InvalidArgumentException('Compte introuvable.');
    if (strlen($password)<12) throw new InvalidArgumentException('Le mot de passe doit contenir au moins 12 caractères.');
    if (strlen($password)>72) throw new InvalidArgumentException('Le mot de passe ne doit pas dépasser 72 octets.');
    if (!hash_equals($password,$confirmation)) throw new InvalidArgumentException('Les deux mots de passe ne correspondent pas.');
    $hash=password_hash($password,PASSWORD_DEFAULT);
    if (!is_string($hash)) throw new InvalidArgumentException('Le mot de passe n’a pas pu être sécurisé.');
    $pdo->prepare('UPDATE users SET password_hash=:hash WHERE id=:id')->execute([':hash'=>$hash,':id'=>$id]);
}

function active_central_count(PDO $pdo, ?int $excludeId = null): int
{
    $sql="SELECT COUNT(*) FROM users WHERE role='central' AND active=1".($excludeId!==null?' AND id<>:id':'');
    $stmt=$pdo->prepare($sql);
    $stmt->execute($excludeId!==null?[':id'=>$excludeId]:[]);
    return (int)$stmt->fetchColumn();
}
