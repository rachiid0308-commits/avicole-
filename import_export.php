<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
if (($user['role'] ?? '') !== ROLE_ADMINISTRATOR) { http_response_code(403); exit('Importer ou exporter les données est réservé à l’administrateur.'); }
require_once __DIR__ . '/admin_data_tools.php';
$pdo = db();

if (isset($_GET['action']) && in_array((string)$_GET['action'], ['export','template'], true)) {
    try {
        $dataset = (string)($_GET['dataset'] ?? 'units');
        $format = strtolower((string)($_GET['format'] ?? 'csv'));
        if (!in_array($format, ['csv','xlsx'], true)) throw new InvalidArgumentException('Format d’export invalide.');
        [$regionId,$provinceId] = admin_data_geo_filter($pdo,$_GET['region_id']??null,$_GET['province_id']??null);
        if ($_GET['action']==='template') {
            if ($dataset==='units') { $headers=admin_data_unit_headers(); $rows=[]; }
            elseif ($dataset==='controls') { $headers=admin_data_report_headers(); $rows=[]; }
            else throw new InvalidArgumentException('Type de données inconnu.');
        } else {
            [$headers,$rows] = admin_data_export_rows($pdo,$dataset,$regionId,$provinceId);
        }
        $name = admin_data_safe_filename(($dataset==='controls'?'rapports-controle':'unites-avicoles').'-'.($provinceId?'province-'.$provinceId:($regionId?'region-'.$regionId:'national')));
        if ($format==='xlsx') admin_data_xlsx_download($name,$dataset==='controls'?'Rapports':'Unités',$headers,$rows);
        admin_data_csv_download($name,$headers,$rows);
    } catch (Throwable $exception) {
        error_log('Import/export — export : '.$exception->getMessage());
        http_response_code($exception instanceof InvalidArgumentException ? 400 : 503);
        exit($exception instanceof InvalidArgumentException ? e($exception->getMessage()) : 'Export indisponible. Vérifiez que les tables du module Contrôles et les extensions PHP PharData/DOM ou ZipArchive sont installées.');
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $dataset=(string)($_POST['dataset']??'units');
    try {
        if (!in_array($dataset,['units','controls'],true)) throw new InvalidArgumentException('Type de données inconnu.');
        [$regionId,$provinceId]=admin_data_geo_filter($pdo,$_POST['region_id']??null,$_POST['province_id']??null);
        $file=$_FILES['datafile']??null;
        if (!$file || (int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name']??''))) throw new InvalidArgumentException('Téléversement impossible. Vérifiez le fichier et les limites PHP.');
        if ((int)($file['size']??0)<1 || (int)$file['size']>10*1024*1024) throw new InvalidArgumentException('Le fichier doit faire au maximum 10 Mo.');
        $extension=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
        if (!in_array($extension,['csv','xlsx'],true)) throw new InvalidArgumentException('Formats acceptés : CSV ou XLSX (classeur Excel).');
        if ($dataset==='units') {
            $matrix=admin_data_read_matrix((string)$file['tmp_name'],$extension);
            $canonical=admin_data_rows_to_legacy_csv($matrix);
            $temp=tempnam(sys_get_temp_dir(),'aviculture-units-');
            if (!is_string($temp) || file_put_contents($temp,$canonical)===false) throw new RuntimeException('Impossible de préparer le fichier unités.');
            try { $result=import_legacy_csv($pdo,$temp,basename((string)$file['name']),$user,$regionId,$provinceId); }
            finally { if (is_file($temp)) unlink($temp); }
            flash('Import des unités terminé : '.$result['imported'].' ajoutée(s), '.($result['duplicates']??0).' doublon(s) ignoré(s), '.$result['demo_skipped'].' ligne(s) de démonstration ignorée(s).');
        } else {
            $result=admin_data_import_control_reports($pdo,(string)$file['tmp_name'],basename((string)$file['name']),$user,$regionId,$provinceId);
            flash('Import des rapports terminé : '.$result['imported'].' ajouté(s), '.$result['duplicates'].' doublon(s) ignoré(s).');
        }
    } catch (Throwable $exception) {
        error_log('Import/export — import : '.$exception->getMessage());
        flash('Import annulé : '.$exception->getMessage(),'error');
    }
    header('Location: import_export.php?dataset='.rawurlencode($dataset));
    exit;
}

$flash=take_flash();
$geo=geography_rows($user);
$dataset=(string)($_GET['dataset']??'units');
if (!in_array($dataset,['units','controls'],true)) $dataset='units';
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light"><title>Importer/Exporter — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header><div class="brand"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><div><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></div></div><?php require __DIR__ . '/navigation.php'; ?></header>
<main><div class="page-heading"><div><h1>Importer / Exporter les données</h1><p class="help">Outil réservé à l’administrateur. Les imports ajoutent de nouvelles lignes ; ils ne modifient ni ne suppriment les données existantes.</p></div></div>
<?php if($flash): ?><div class="flash <?=e($flash['type']==='error'?'error':'')?>" role="status"><?=e($flash['message'])?></div><?php endif; ?>
<div class="card"><h2>Données à importer</h2><p>Choisissez le type de données pour le formulaire d’import. Pour chaque opération, sélectionnez la région ou la province ; le serveur applique et valide cette portée.</p><form class="toolbar" method="get" action="import_export.php#import"><label>Jeu de données<select name="dataset" id="dataset"><option value="units" <?=$dataset==='units'?'selected':''?>>Unités avicoles</option><option value="controls" <?=$dataset==='controls'?'selected':''?>>Rapports de contrôle</option></select></label><button class="btn" type="submit">Sélectionner</button></form></div>
<div class="card"><h2>Exporter</h2><p>Le classeur Excel contient une feuille avec les données filtrées. Le CSV est encodé en UTF-8 avec séparateur point-virgule, compatible avec Excel.</p><form class="toolbar" method="get" action="import_export.php"><input type="hidden" name="action" value="export"><label>Données<select name="dataset"><option value="units" <?=$dataset==='units'?'selected':''?>>Unités avicoles</option><option value="controls" <?=$dataset==='controls'?'selected':''?>>Rapports de contrôle</option></select></label><label>Région<select name="region_id"><option value="">Toutes les régions</option><?php foreach($geo['regions'] as $id=>$name): ?><option value="<?=$id?>"><?=e($name)?></option><?php endforeach; ?></select></label><label>Province / préfecture<select name="province_id"><option value="">Toutes les provinces</option><?php foreach($geo['rows'] as $row): ?><option value="<?=(int)$row['province_id']?>" data-region="<?=(int)$row['region_id']?>"><?=e($row['province_name'])?> · <?=e($row['region_name'])?></option><?php endforeach; ?></select></label><label>Format<select name="format"><option value="xlsx">Excel (.xlsx)</option><option value="csv">CSV (.csv)</option></select></label><button class="btn" type="submit">Télécharger l’export</button></form></div>
<div class="card" id="import"><h2>Importer</h2><p>Choisissez le même jeu de données et la zone correspondant au fichier. Les lignes hors zone, invalides ou déjà présentes font échouer le lot ou sont ignorées comme doublons ; l’import des rapports est transactionnel.</p><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="dataset" value="<?=e($dataset)?>"><div class="formgrid"><label>Région cible (facultatif)<select name="region_id"><option value="">Toutes les régions du fichier</option><?php foreach($geo['regions'] as $id=>$name): ?><option value="<?=$id?>"><?=e($name)?></option><?php endforeach; ?></select></label><label>Province / préfecture cible<select name="province_id"><option value="">Toutes les provinces de la région</option><?php foreach($geo['rows'] as $row): ?><option value="<?=(int)$row['province_id']?>" data-region="<?=(int)$row['region_id']?>"><?=e($row['province_name'])?> · <?=e($row['region_name'])?></option><?php endforeach; ?></select></label><label class="wide">Fichier CSV ou Excel (.xlsx), 10 Mo maximum<input type="file" name="datafile" accept=".csv,text/csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label></div><p><button class="btn" type="submit">Importer les nouvelles données</button></p></form><p class="help">Unités : fichier avec 14 colonnes issu du modèle. Rapports : colonnes région, province, type (chair, reproducteurs, pondeuses ou transport), date, unité, commission, métadonnées JSON, réponses JSON et membres JSON. Les identifiants internes sont recréés sur cette base.</p><div class="actions"><a class="btn secondary" href="import_export.php?action=template&amp;dataset=units&amp;format=csv">Modèle unités CSV</a><a class="btn secondary" href="import_export.php?action=template&amp;dataset=units&amp;format=xlsx">Modèle unités Excel</a><a class="btn secondary" href="import_export.php?action=template&amp;dataset=controls&amp;format=csv">Modèle rapports CSV</a><a class="btn secondary" href="import_export.php?action=template&amp;dataset=controls&amp;format=xlsx">Modèle rapports Excel</a></div></div>
</main><footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer>
<script>
for(const form of document.querySelectorAll('form')){const region=form.querySelector('select[name="region_id"]'),province=form.querySelector('select[name="province_id"]');if(!region||!province)continue;const filter=()=>{for(const option of province.options){if(!option.value)continue;option.hidden=!!region.value&&option.dataset.region!==region.value;if(option.hidden&&option.selected)province.value='';}};region.addEventListener('change',filter);filter();}
</script><script src="assets/idle-timeout.js" defer></script></body></html>
