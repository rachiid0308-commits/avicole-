<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($id && !can_manage_farm($user)) { http_response_code(403); exit('Profil en consultation seule.'); }
if (!$id && !can_create_farm($user)) { http_response_code(403); exit('Seul un chef de service ou l’administrateur peut ajouter une unité.'); }
$farm = null;
if ($id) { $farm = farm_by_id($id, $user); if (!$farm) { http_response_code(404); exit('Unité introuvable ou non autorisée.'); } }
$geo = geography_rows($user);
$flash = take_flash();
$regions = $geo['regions'];
$selectedRegion = $farm ? (int)$farm['region_id'] : (int)($user['region_id'] ?? 0);
$selectedProvince = $farm ? (int)$farm['province_id'] : (int)($user['province_id'] ?? 0);
$provinceLabel = $selectedProvince ? ($farm['province_name'] ?? $user['province_name'] ?? '') : '';
function field_value(array|null $farm, string $key): string { return e($farm[$key] ?? ''); }
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light"><title><?=$farm?'Modifier':'Ajouter'?> — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header><div class="brand"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><div><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></div></div><?php require __DIR__ . '/navigation.php'; ?><a class="btn secondary" href="units.php">← Retour à la liste</a></header>
<main><?php if ($flash): ?><div class="flash <?=e($flash['type'] === 'error' ? 'error' : '')?>"><?=e($flash['message'])?></div><?php endif; ?><div class="card"><h1><?=$farm?'Modifier une unité':'Ajouter une unité avicole'?></h1><p class="help">Le serveur vérifie que la région / province est dans votre périmètre d’accès.</p>
<form method="post" action="save_farm.php"><?=csrf_field()?><?php if ($farm): ?><input type="hidden" name="id" value="<?=(int)$farm['id']?>"><?php endif; ?>
<div class="formgrid">
<label>Région
<?php if (has_global_scope($user)): ?><select name="region_id" id="region" required><option value="">Choisir…</option><?php foreach ($regions as $rid=>$rname): ?><option value="<?=$rid?>" <?=$selectedRegion===$rid?'selected':''?>><?=e($rname)?></option><?php endforeach; ?></select>
<?php else: ?><input value="<?=e($regions[$selectedRegion] ?? $user['region_name'] ?? '')?>" disabled><input type="hidden" name="region_id" value="<?=$selectedRegion?>"><input type="hidden" id="region" value="<?=$selectedRegion?>"><?php endif; ?></label>
<label>Province / préfecture
<?php if (has_province_scope($user)): ?><input value="<?=e($provinceLabel)?>" disabled><input type="hidden" name="province_id" value="<?=$selectedProvince?>">
<?php else: ?><select name="province_id" id="province" required><option value="">Choisir…</option><?php foreach ($geo['rows'] as $g): ?><option value="<?=$g['province_id']?>" data-region="<?=$g['region_id']?>" <?=$selectedProvince===(int)$g['province_id']?'selected':''?>><?=e($g['province_name'])?></option><?php endforeach; ?></select><?php endif; ?></label>
<label>Statut d’autorisation<select name="authorization_status" required><?php foreach (FARM_STATUSES as $status): ?><option value="<?=e($status)?>" <?=($farm['authorization_status'] ?? 'Accord de principe')===$status?'selected':''?>><?=e($status)?></option><?php endforeach; ?></select></label>
<label>N° accord<input name="agreement_number" maxlength="100" value="<?=field_value($farm,'agreement_number')?>"></label>
<label>N° autorisation<input name="authorization_number" maxlength="100" value="<?=field_value($farm,'authorization_number')?>"></label>
<label>Propriétaire (nom et prénom)<input name="owner_name" maxlength="190" value="<?=field_value($farm,'owner_name')?>"></label>
<label>N° CNIE<input name="national_id" maxlength="50" value="<?=field_value($farm,'national_id')?>"></label>
<label>Nom de l’unité<input name="farm_name" maxlength="190" required value="<?=field_value($farm,'farm_name')?>"></label>
<label>Latitude<input name="latitude" type="number" step="any" min="-90" max="90" required value="<?=field_value($farm,'latitude')?>"></label>
<label>Longitude<input name="longitude" type="number" step="any" min="-180" max="180" required value="<?=field_value($farm,'longitude')?>"></label>
<label>Type d’élevage<select name="farm_type" required><?php foreach (FARM_TYPES as $type): ?><option value="<?=e($type)?>" <?=($farm['farm_type'] ?? 'Chair')===$type?'selected':''?>><?=e($type)?></option><?php endforeach; ?></select></label>
<label>Date du 1er accord<input name="first_agreement_date" type="date" value="<?=field_value($farm,'first_agreement_date')?>"></label>
<label>Date du 2e accord<input name="second_agreement_date" type="date" value="<?=field_value($farm,'second_agreement_date')?>"></label>
<label>Délai de fin de construction<input name="construction_deadline" type="date" value="<?=field_value($farm,'construction_deadline')?>"></label>
</div><p class="help">Repères de délai : le 2e accord est attendu dans le mois suivant le 1er accord ; la fin des travaux est attendue dans l’année suivant le 2e accord. Les échéances dépassées apparaissent en rouge dans la liste.</p><p class="actions"><button class="btn" type="submit">Enregistrer</button><a class="btn secondary" href="units.php">Annuler</a></p></form>
<?php if ($farm): ?><form method="post" action="delete_farm.php" onsubmit="return confirm('Supprimer cette unité ? Cette action est irréversible.');"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$farm['id']?>"><button class="btn danger" type="submit">Supprimer l’unité</button></form><?php endif; ?></div></main>
<?php if (!has_province_scope($user)): ?><script>
const region=document.getElementById('region'), province=document.getElementById('province');
function filterProvinces(){const selected=province.value;for(const option of province.options){if(!option.value)continue;option.hidden=!!region.value&&option.dataset.region!==region.value;}if(selected&&province.selectedOptions[0]?.hidden)province.value='';}
region.addEventListener('change',filterProvinces);filterProvinces();
</script><?php endif; ?><footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer><script src="assets/idle-timeout.js" defer></script></body></html>
