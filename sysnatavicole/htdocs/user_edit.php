<?php
declare(strict_types=1);
require_once __DIR__ . '/account_manager.php';

$user = require_auth();
if (($user['role'] ?? '') !== ROLE_ADMINISTRATOR) { http_response_code(403); exit('La gestion des comptes est réservée à l’administrateur.'); }

$pdo = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$account = $id ? account_by_id($pdo, (int)$id) : null;
if (!$account || !can_manage_account($user,$account)) { http_response_code(404); exit('Compte introuvable ou hors de votre périmètre.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $redirectToAccounts = false;
    try {
        if ($action === 'password') {
            set_account_password($pdo, (int)$id, (string)($_POST['password'] ?? ''), (string)($_POST['password_confirmation'] ?? ''));
            flash('Mot de passe mis à jour. Communiquez-le à la personne par un canal séparé.');
            $redirectToAccounts = true;
        } elseif ($action === 'profile') {
            $isSelf = (int)$account['id'] === (int)$user['id'];
            $role = $isSelf ? (string)$account['role'] : (string)($_POST['role'] ?? '');
            if ($isSelf) {
                $regionId = $account['region_id'] !== null ? (int)$account['region_id'] : null;
                $provinceId = $account['province_id'] !== null ? (int)$account['province_id'] : null;
            } else {
                $regionText = trim((string)($_POST['region_id'] ?? ''));
                $provinceText = trim((string)($_POST['province_id'] ?? ''));
                $regionId = $regionText === '' ? null : filter_var($regionText, FILTER_VALIDATE_INT);
                $provinceId = $provinceText === '' ? null : filter_var($provinceText, FILTER_VALIDATE_INT);
                if ($regionId === false || $provinceId === false) throw new InvalidArgumentException('Affectation géographique invalide.');
            }
            update_account_for_manager($pdo,$user,(int)$id,(string)($_POST['email']??''),(string)($_POST['full_name']??''),$role,$regionId,$provinceId,(string)($_POST['mobile_phone']??''),(string)($_POST['professional_title']??''));
            flash('Compte mis à jour.');
        } else {
            throw new InvalidArgumentException('Action inconnue.');
        }
    } catch (InvalidArgumentException $ex) {
        flash($ex->getMessage(), 'error');
    } catch (Throwable $ex) {
        error_log((string)$ex);
        flash('Modification impossible. Vérifiez les informations saisies.', 'error');
    }
    header('Location: ' . ($redirectToAccounts ? 'users.php' : 'user_edit.php?id=' . (int)$id));
    exit;
}

$flash = take_flash();
$account = account_by_id($pdo, (int)$id);
$isSelf = (int)$account['id'] === (int)$user['id'];
$geo = geography_rows($user);
$regions = $geo['regions'];
$roleLabels = [ROLE_CENTRAL=>'Directeur central',ROLE_ADMINISTRATOR=>'Administrateur',ROLE_CENTRAL_VIEWER=>'Profil central consultation',ROLE_REGIONAL=>'Directeur régional',ROLE_DIVISION_HEAD=>'Chef de division',ROLE_PROVINCIAL=>'Chef de service',ROLE_VETERINARY_RESPONSIBLE=>'Vétérinaire'];
$roleOptions = managed_account_roles($user);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light"><title>Modifier un compte — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header class="site-header"><a class="brand" href="index.php"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><span><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></span></a><?php require __DIR__ . '/navigation.php'; ?><a class="btn secondary" href="users.php">← Comptes</a></header>
<main><?php if ($flash): ?><div class="flash <?=e($flash['type'] === 'error' ? 'error' : '')?>"><?=e($flash['message'])?></div><?php endif; ?>
<div class="card"><h1>Modifier le compte</h1><p class="help"><?=e($account['email'])?> · <?=e($roleLabels[$account['role']])?> · <?=((int)$account['active'] === 1) ? 'Actif' : 'Désactivé'?></p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="profile"><div class="formgrid">
<label>Nom complet<input name="full_name" maxlength="160" required value="<?=e($account['full_name'])?>"></label>
<label>Titre professionnel<input name="professional_title" maxlength="120" autocomplete="organization-title" value="<?=e($account['professional_title']??'')?>"></label>
<label>Adresse e-mail professionnelle<input name="email" type="email" maxlength="190" pattern="[A-Za-z0-9._%+-]+@onssa\.gov\.ma" placeholder="nom@onssa.gov.ma" required value="<?=e($account['email'])?>"></label>
<label>N° mobile (10 chiffres)<input name="mobile_phone" type="tel" inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10" placeholder="0612345678" autocomplete="tel" required value="<?=e($account['mobile_phone']??'')?>"></label>
<label>Profil<select name="role" id="role" <?=$isSelf ? 'disabled' : ''?> required><?php foreach($roleOptions as $roleOption): ?><option value="<?=e($roleOption)?>" <?=$account['role']===$roleOption?'selected':''?>><?=e($roleLabels[$roleOption]??$roleOption)?></option><?php endforeach; ?></select></label>
<label id="regionLabel">Région<select name="region_id" id="region" <?=$isSelf ? 'disabled' : ''?>><option value="">Choisir…</option><?php foreach ($regions as $rid => $rname): ?><option value="<?=$rid?>" <?=(int)$account['region_id'] === (int)$rid ? 'selected' : ''?>><?=e($rname)?></option><?php endforeach; ?></select></label>
<label id="provinceLabel">Province / préfecture<select name="province_id" id="province" <?=$isSelf ? 'disabled' : ''?>><option value="">Choisir…</option><?php foreach ($geo['rows'] as $g): ?><option value="<?=$g['province_id']?>" data-region="<?=$g['region_id']?>" <?=(int)$account['province_id'] === (int)$g['province_id'] ? 'selected' : ''?>><?=e($g['province_name'])?> · <?=e($g['region_name'])?></option><?php endforeach; ?></select></label>
</div><?php if ($isSelf): ?><p class="help">Votre propre profil et votre affectation ne peuvent pas être modifiés ici.</p><?php endif; ?>
<p><button class="btn" type="submit">Enregistrer les modifications</button> <a class="btn secondary" href="users.php">Annuler</a></p></form></div>
<div class="card" id="mot-de-passe"><h2>Réinitialiser le mot de passe</h2><p class="help">12 caractères minimum. Le mot de passe n’est jamais réaffiché ensuite : communiquez-le par un canal séparé.</p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="password"><div class="formgrid">
<label>Nouveau mot de passe<input name="password" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
<label>Confirmer le mot de passe<input name="password_confirmation" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
</div><p><button class="btn" type="submit">Mettre à jour le mot de passe</button></p></form></div>
</main>
<script>
document.querySelectorAll('input[name="mobile_phone"]').forEach(input=>input.addEventListener('input',()=>{input.value=input.value.replace(/[^0-9]/g,'').slice(0,10)}));
const role = document.getElementById('role'), region = document.getElementById('region'), province = document.getElementById('province');
const regionLabel = document.getElementById('regionLabel'), provinceLabel = document.getElementById('provinceLabel');
function adjust() {
  const r = role.value, global = ['central','administrator','central_viewer'].includes(r), provinceRole = ['provincial','veterinary_responsible'].includes(r);
  regionLabel.style.display = global ? 'none' : 'flex';
  provinceLabel.style.display = provinceRole ? 'flex' : 'none';
  region.disabled = role.disabled || global;
  region.required = !role.disabled && !global;
  province.disabled = role.disabled || !provinceRole;
  province.required = !role.disabled && provinceRole;
  for (const option of province.options) {
    if (option.value) option.hidden = !!region.value && option.dataset.region !== region.value;
  }
  if (province.selectedOptions[0] && province.selectedOptions[0].hidden) province.value = '';
}
role.addEventListener('change', adjust);
region.addEventListener('change', adjust);
adjust();
</script>
<footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer><script src="assets/idle-timeout.js" defer></script></body></html>
