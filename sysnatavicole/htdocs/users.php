<?php
declare(strict_types=1);
require_once __DIR__.'/account_manager.php';
$user=require_auth();
$isAdmin=($user['role']??'')===ROLE_ADMINISTRATOR;
$pdo=db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!$isAdmin) { http_response_code(403); exit('Seul l’administrateur peut gérer les comptes.'); }
    verify_csrf();
    $action=(string)($_POST['action']??'create');
    try {
        if ($action==='toggle') {
            $targetId=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);
            $account=$targetId?account_by_id($pdo,(int)$targetId):null;
            if (!$account || !can_manage_account($user,$account)) throw new InvalidArgumentException('Compte introuvable ou hors de votre périmètre.');
            $activate=(string)($_POST['active']??'')==='1';
            if (!$activate && (int)$targetId===(int)$user['id']) throw new InvalidArgumentException('Vous ne pouvez pas désactiver votre propre compte.');
            if (!$activate && $account['role']===ROLE_CENTRAL && active_central_count($pdo,(int)$targetId)===0) throw new InvalidArgumentException('Au moins un directeur central doit rester actif.');
            set_account_active($pdo,(int)$targetId,$activate);
            flash($activate?'Compte réactivé. La personne peut de nouveau se connecter.':'Compte désactivé. La personne ne peut plus se connecter.');
        } elseif ($action==='password') {
            $targetId=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);
            $account=$targetId?account_by_id($pdo,(int)$targetId):null;
            if (!$account || !can_manage_account($user,$account)) throw new InvalidArgumentException('Compte introuvable ou hors de votre périmètre.');
            set_account_password($pdo,(int)$targetId,(string)($_POST['password']??''),(string)($_POST['password_confirmation']??''));
            flash('Mot de passe mis à jour. Communiquez-le à la personne par un canal séparé.');
        } elseif ($action==='delete') {
            $targetId=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);
            $account=$targetId?account_by_id($pdo,(int)$targetId):null;
            if (!$account || !can_manage_account($user,$account)) throw new InvalidArgumentException('Compte introuvable ou hors de votre périmètre.');
            if ((int)$account['id']===(int)$user['id']) throw new InvalidArgumentException('Vous ne pouvez pas supprimer votre propre compte.');
            if ($account['role']===ROLE_CENTRAL && active_central_count($pdo,(int)$targetId)===0) throw new InvalidArgumentException('Au moins un directeur central actif doit rester.');
            delete_profile_account($pdo,(int)$targetId);
            flash('Compte supprimé.');
        } else {
            $role=(string)($_POST['role']??'');
            $regionText=trim((string)($_POST['region_id']??''));
            $provinceText=trim((string)($_POST['province_id']??''));
            $regionId=$regionText===''?null:filter_var($regionText,FILTER_VALIDATE_INT);
            $provinceId=$provinceText===''?null:filter_var($provinceText,FILTER_VALIDATE_INT);
            if ($regionId===false || $provinceId===false) throw new InvalidArgumentException('Affectation géographique invalide.');
            create_account_for_manager($pdo,$user,(string)($_POST['email']??''),(string)($_POST['full_name']??''),$role,$regionId,$provinceId,(string)($_POST['password']??''),(string)($_POST['mobile_phone']??''),(string)($_POST['professional_title']??''));
            flash('Compte créé. Communiquez son mot de passe initial à la personne par un canal séparé.');
        }
    } catch (InvalidArgumentException $ex) { flash($ex->getMessage(),'error'); }
      catch (Throwable $ex) { error_log((string)$ex); flash('Opération impossible. Vérifiez les informations saisies.','error'); }
    header('Location: users.php'); exit;
}
$flash=take_flash();
$geo=geography_rows($user);
$regions=$geo['regions'];
[$accountScope,$accountParams]=account_list_scope($user,'u');
$accountFilterWhere=$accountScope;
$accountFilterParams=$accountParams;
$accountRegionFilter='';$accountProvinceFilter='';
if (isset($_GET['region_id']) && trim((string)$_GET['region_id'])!=='') {
    $regionValue=filter_var($_GET['region_id'],FILTER_VALIDATE_INT);
    $accountRegionFilter=$regionValue!==false&&$regionValue>0?(string)$regionValue:'0';
    $accountFilterWhere.=' AND u.region_id=:account_filter_region';
    $accountFilterParams[':account_filter_region']=(int)$accountRegionFilter;
}
if (isset($_GET['province_id']) && trim((string)$_GET['province_id'])!=='') {
    $provinceValue=filter_var($_GET['province_id'],FILTER_VALIDATE_INT);
    $accountProvinceFilter=$provinceValue!==false&&$provinceValue>0?(string)$provinceValue:'0';
    $accountFilterWhere.=' AND u.province_id=:account_filter_province';
    $accountFilterParams[':account_filter_province']=(int)$accountProvinceFilter;
}
$counts=array_fill_keys(['central','administrator','central_viewer','regional','division_head','provincial','veterinary_responsible'],0);
$countStmt=$pdo->prepare("SELECT u.role,COUNT(*) AS n FROM users u WHERE {$accountScope} GROUP BY u.role"); $countStmt->execute($accountParams);
foreach ($countStmt as $row) if (isset($counts[$row['role']])) $counts[$row['role']]=(int)$row['n'];
$accountCountStmt=$pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$accountFilterWhere}"); $accountCountStmt->execute($accountFilterParams); $totalAccounts=(int)$accountCountStmt->fetchColumn();
$perPage=20; $totalPages=max(1,(int)ceil($totalAccounts/$perPage));
$pageValue=filter_var($_GET['page']??1,FILTER_VALIDATE_INT); $currentPage=$pageValue===false||$pageValue<1?1:min($pageValue,$totalPages); $offset=($currentPage-1)*$perPage;
$accountStmt=$pdo->prepare("SELECT u.id,u.email,u.full_name,u.professional_title,u.mobile_phone,u.role,u.region_id,u.province_id,r.name AS region_name,p.name AS province_name,u.active FROM users u LEFT JOIN regions r ON r.id=u.region_id LEFT JOIN provinces p ON p.id=u.province_id WHERE {$accountFilterWhere} ORDER BY u.role,r.name,p.name,u.full_name LIMIT :limit OFFSET :offset");
foreach($accountFilterParams as $key=>$value) $accountStmt->bindValue($key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR); $accountStmt->bindValue(':limit',$perPage,PDO::PARAM_INT); $accountStmt->bindValue(':offset',$offset,PDO::PARAM_INT); $accountStmt->execute(); $accounts=$accountStmt->fetchAll();
$roleTitles=[ROLE_CENTRAL=>'Directeur central',ROLE_ADMINISTRATOR=>'Administrateur',ROLE_CENTRAL_VIEWER=>'Profil central consultation',ROLE_REGIONAL=>'Directeur régional',ROLE_DIVISION_HEAD=>'Chef de division',ROLE_PROVINCIAL=>'Chef de service',ROLE_VETERINARY_RESPONSIBLE=>'Vétérinaire'];
$creationRoles=managed_account_roles($user);
$displayRoles=$isAdmin?$creationRoles:array_keys($roleTitles);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light"><title><?= $isAdmin?'Gestion des comptes':'Comptes de votre périmètre' ?> — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header class="site-header"><a class="brand" href="index.php"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><span><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></span></a><?php require __DIR__ . '/navigation.php'; ?><a class="btn secondary" href="index.php">← Retour</a></header>
<main><?php if($flash):?><div class="flash <?=e($flash['type']==='error'?'error':'')?>"><?=e($flash['message'])?></div><?php endif;?>
<div class="card"><h1><?= $isAdmin?'Gestion des comptes':'Comptes de votre périmètre' ?></h1><p class="help"><?= $isAdmin?'Seul l’administrateur peut créer, modifier, désactiver, supprimer des comptes ou réinitialiser un mot de passe.':'Consultation seule : les comptes affichés sont limités à '.(has_global_scope($user)?'l’ensemble du territoire':(has_region_scope($user)?'votre région ('.e($user['region_name']??'').')':(has_province_scope($user)?'votre province / préfecture ('.e($user['province_name']??'').')':'votre périmètre'))).'.' ?></p><div class="stats"><?php foreach($displayRoles as $role): ?><div class="stat"><b><?=$counts[$role]??0?></b><?=e($roleTitles[$role]??$role)?></div><?php endforeach; ?></div></div>
<?php if($isAdmin): ?><div class="card"><h2>Créer un compte</h2><p class="help">Mot de passe initial : 12 caractères minimum.</p>
<form method="post"><?=csrf_field()?><div class="formgrid">
<label>Profil<select name="role" id="role" required><?php foreach($creationRoles as $role): ?><option value="<?=e($role)?>"><?=e($roleTitles[$role]??$role)?></option><?php endforeach; ?></select></label>
<label id="regionLabel">Région<select name="region_id" id="region"><option value="">Choisir…</option><?php foreach($regions as $rid=>$rname):?><option value="<?=$rid?>"><?=e($rname)?></option><?php endforeach;?></select></label>
<label id="provinceLabel">Province / préfecture<select name="province_id" id="province"><option value="">Choisir…</option><?php foreach($geo['rows'] as $g):?><option value="<?=$g['province_id']?>" data-region="<?=$g['region_id']?>"><?=e($g['province_name'])?> · <?=e($g['region_name'])?></option><?php endforeach;?></select></label>
<label>Nom complet<input name="full_name" maxlength="160" required></label>
<label>Titre professionnel<input name="professional_title" maxlength="120" autocomplete="organization-title"></label>
<label>Adresse e-mail professionnelle<input name="email" type="email" maxlength="190" pattern="[A-Za-z0-9._%+-]+@onssa\.gov\.ma" placeholder="nom@onssa.gov.ma" required></label>
<label>N° mobile (10 chiffres)<input name="mobile_phone" type="tel" inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10" placeholder="0612345678" autocomplete="tel" required></label>
<label>Mot de passe initial<input name="password" type="password" minlength="12" required autocomplete="new-password"></label>
</div><p><button class="btn" type="submit">Créer le compte</button></p></form></div><?php endif; ?>
<div class="card"><h2>Comptes enregistrés</h2><p class="help"><?= $isAdmin?'Gérez les profils et les accès des comptes enregistrés.':'Liste en lecture seule des profils de votre zone.' ?></p>
<form class="toolbar account-search" method="get" action="users.php"><label>Région<select name="region_id" id="account-region-filter"><option value="">Toutes les régions visibles</option><?php foreach($regions as $regionId=>$regionName): ?><option value="<?=(int)$regionId?>" <?=$accountRegionFilter===(string)$regionId?'selected':''?>><?=e($regionName)?></option><?php endforeach; ?></select></label><label>Province / préfecture<select name="province_id" id="account-province-filter"><option value="">Toutes les provinces visibles</option><?php foreach($geo['rows'] as $row): ?><option value="<?=(int)$row['province_id']?>" data-region="<?=(int)$row['region_id']?>" <?=$accountProvinceFilter===(string)$row['province_id']?'selected':''?>><?=e($row['province_name'])?> · <?=e($row['region_name'])?></option><?php endforeach; ?></select></label><div class="toolbar-actions"><button class="btn" type="submit">Rechercher</button><a class="btn secondary" href="users.php">Effacer</a></div><span class="help"><?=$totalAccounts?> résultat(s)</span></form>
<div class="tablewrap"><table class="accounts-table"><thead><tr><th>Nom</th><th>Titre professionnel</th><th>E-mail</th><th>N° mobile</th><th>Profil</th><th>État</th><th>Affectation</th><?php if($isAdmin): ?><th class="row-actions">Actions</th><?php endif; ?></tr></thead><tbody>
<?php foreach($accounts as $account): $accountActive=(int)$account['active']===1; ?>
<tr><td class="cell-name"><?=e($account['full_name'])?></td><td data-label="Titre professionnel"><?=e($account['professional_title']??'')?></td><td data-label="E-mail"><?=e($account['email'])?></td><td data-label="N° mobile"><?=e($account['mobile_phone']??'')?></td><td data-label="Profil"><?=e($roleTitles[$account['role']]??$account['role'])?></td><td data-label="État"><span class="badge<?= $accountActive?'':' off'?>"><?= $accountActive?'Actif':'Désactivé'?></span></td><td data-label="Affectation"><?=e($account['province_name']?:$account['region_name']?:'National')?></td>
<?php if($isAdmin): ?><td class="row-actions"><a class="btn secondary btn-sm" href="user_edit.php?id=<?=(int)$account['id']?>">Modifier</a><a class="btn secondary btn-sm" href="user_edit.php?id=<?=(int)$account['id']?>#mot-de-passe">Mot de passe</a><?php if ($accountActive && (int)$account['id'] === (int)$user['id']): ?><button class="btn secondary btn-sm" type="button" disabled title="Vous ne pouvez pas désactiver votre propre compte.">Désactiver</button><?php else: ?><form method="post" class="inline-form" onsubmit="return confirm('<?= $accountActive ? 'Désactiver ce compte ? La personne ne pourra plus se connecter.' : 'Réactiver ce compte ?' ?>');"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?=(int)$account['id']?>"><input type="hidden" name="active" value="<?= $accountActive?'0':'1'?>"><button class="btn <?= $accountActive?'danger':'secondary'?> btn-sm" type="submit"><?= $accountActive?'Désactiver':'Réactiver'?></button></form><?php endif; ?><?php if((int)$account['id']!==(int)$user['id']): ?><form method="post" class="inline-form" onsubmit="return confirm('Supprimer définitivement ce compte ? Les références historiques resteront sans créateur.');"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?=(int)$account['id']?>"><button class="btn danger btn-sm" type="submit">Supprimer</button></form><?php endif; ?></td><?php endif; ?></tr>
<?php endforeach;?></tbody></table></div></div><?=pagination_html('Pagination des comptes','page',$currentPage,$totalAccounts,$perPage,'compte(s)')?>
</main><?php if($isAdmin): ?><script>
document.querySelectorAll('input[name="mobile_phone"]').forEach(input=>input.addEventListener('input',()=>{input.value=input.value.replace(/[^0-9]/g,'').slice(0,10)}));
const role=document.getElementById('role'),region=document.getElementById('region'),province=document.getElementById('province'),regionLabel=document.getElementById('regionLabel'),provinceLabel=document.getElementById('provinceLabel');
function adjust(){const r=role.value,global=['central','administrator','central_viewer'].includes(r),provinceRole=['provincial','veterinary_responsible'].includes(r);regionLabel.style.display=global?'none':'flex';provinceLabel.style.display=provinceRole?'flex':'none';region.disabled=global;region.required=!global;province.disabled=!provinceRole;province.required=provinceRole;for(const option of province.options){if(option.value)option.hidden=!!region.value&&option.dataset.region!==region.value;}if(province.selectedOptions[0]?.hidden)province.value='';}
role.addEventListener('change',adjust);region.addEventListener('change',adjust);adjust();
</script><?php endif; ?><script>
const accountRegionFilter=document.getElementById('account-region-filter'),accountProvinceFilter=document.getElementById('account-province-filter');
function filterAccountProvinceOptions(){if(!accountRegionFilter||!accountProvinceFilter)return;for(const option of accountProvinceFilter.options){if(!option.value)continue;option.hidden=!!accountRegionFilter.value&&option.dataset.region!==accountRegionFilter.value;}if(accountProvinceFilter.selectedOptions[0]?.hidden)accountProvinceFilter.value='';}
if(accountRegionFilter&&accountProvinceFilter){accountRegionFilter.addEventListener('change',filterAccountProvinceOptions);filterAccountProvinceOptions();}
</script><footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer><script src="assets/idle-timeout.js" defer></script></body></html>
