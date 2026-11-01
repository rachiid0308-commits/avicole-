<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
require_once __DIR__ . '/controls_domain.php';
$controlView = ($controlView ?? '') === 'vehicles' ? 'vehicles' : 'units';
$viewScope = $controlView === 'vehicles' ? " AND cr.inspection_type = 'transport'" : " AND cr.inspection_type <> 'transport'";
[$scope, $params] = scope_clause($user, 'cr');
$reports = [];
$totalReports = 0;
$perPage = 20;
$currentPage = 1;
$schemaMissing = false;
try {
    $countStmt = db()->prepare("SELECT COUNT(*) FROM control_reports cr WHERE {$scope}{$viewScope}");
    $countStmt->execute($params);
    $totalReports = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalReports / $perPage));
    $pageValue = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
    $currentPage = $pageValue === false || $pageValue < 1 ? 1 : min($pageValue, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    $stmt = db()->prepare("SELECT cr.id, cr.inspection_type, cr.inspection_date, cr.unit_name, cr.authorization_number, cr.owner_name, cr.metadata_json, cr.created_at, r.name AS region_name, p.name AS province_name, u.full_name AS created_by_name FROM control_reports cr JOIN regions r ON r.id=cr.region_id JOIN provinces p ON p.id=cr.province_id LEFT JOIN users u ON u.id=cr.created_by WHERE {$scope}{$viewScope} ORDER BY cr.inspection_date DESC, cr.id DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reports = $stmt->fetchAll();
} catch (PDOException $exception) {
    $message = strtolower($exception->getMessage());
    $tableMissing = str_contains($message, 'control_reports')
        && (str_contains($message, 'no such table') || str_contains($message, "doesn't exist") || str_contains($message, 'does not exist'));
    if (!$tableMissing) throw $exception;
    http_response_code(503);
    $schemaMissing = true;
}
$types = control_checklists();
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><title><?= $controlView === 'vehicles' ? 'Contrôle des véhicules' : 'Contrôle des unités' ?> — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="stylesheet" href="assets/controls.css"><link rel="stylesheet" href="assets/control-reports.css"><link rel="stylesheet" href="assets/transport-controls.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header><div class="brand"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><div><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></div></div><?php require __DIR__ . '/navigation.php'; ?></header>
<main class="controls-list-main"><div class="page-heading"><div><h1><?= $controlView === 'vehicles' ? 'Contrôle des véhicules' : 'Contrôle des unités avicoles' ?></h1><p class="help">Les fiches sont affichées selon le périmètre de votre profil; la création et la gestion sont réservées aux profils autorisés.</p></div><div class="actions"><?php if (!$schemaMissing && $controlView === 'units' && can_create_farm($user)): ?><a class="btn secondary" href="farm_form.php">＋ Ajouter une unité</a><?php endif; ?><?php if (!$schemaMissing && can_manage_controls($user)): ?><a class="btn" href="control_form.php?type=<?= $controlView === 'vehicles' ? 'transport' : '' ?>">＋ Nouveau contrôle <?= $controlView === 'vehicles' ? 'de véhicule' : 'd’unité' ?></a><?php endif; ?></div></div>
<div class="card"><h2>Rapports enregistrés</h2><?php if ($schemaMissing): ?><div class="controls-setup" role="alert"><h3>Le module Contrôles n’est pas encore installé sur cette base.</h3><p>Les fichiers du site sont présents, mais la table <code>control_reports</code> manque dans la base de données. Sur un site déjà installé, importez le fichier SQL du module une seule fois dans la base de production :</p><p><a class="btn secondary" href="database/controls.sql" download>Télécharger database/controls.sql</a></p><ol><li>Ouvrez phpMyAdmin et sélectionnez la base utilisée par le site.</li><li>Importez <code>database/controls.sql</code>, puis rechargez cette page.</li></ol><p class="help">Pour une installation neuve, utilisez plutôt le schéma complet <code>database/schema.sql</code>.</p></div><?php elseif (!$reports): ?><p class="help">Aucun rapport de contrôle <?= $controlView === 'vehicles' ? 'de véhicule' : 'd’unité' ?> n’est encore enregistré dans votre périmètre.</p><?php else: ?>
<?php
$unitReports = array_values(array_filter($reports, static fn(array $report): bool => $report['inspection_type'] !== 'transport'));
$transportReports = array_values(array_filter($reports, static fn(array $report): bool => $report['inspection_type'] === 'transport'));
$reportGroups = $controlView === 'vehicles'
    ? [['title' => 'Rapports de contrôle des véhicules', 'reports' => $transportReports]]
    : [['title' => 'Rapports de contrôle des unités avicoles', 'reports' => $unitReports]];
?>
<?php foreach ($reportGroups as $group): ?><?php if (!$group['reports']) continue; $transportGroup = ($group['reports'][0]['inspection_type'] ?? '') === 'transport'; ?>
<section class="control-report-group"><h3><?=e($group['title'])?></h3><div class="tablewrap"><table class="units-table<?=$transportGroup?' transport-reports':''?>"><thead><tr><th>Date</th><th>Type</th><th>Région</th><th>Province / préfecture</th><th><?=$transportGroup?'N° immatriculation':'Unité'?></th><th>N° autorisation</th><th>Propriétaire</th><th>Contrôleur</th><?php if($transportGroup): ?><th>Date d’expiration</th><?php endif; ?><th>Rapport</th><?php if($transportGroup): ?><th>Photos du véhicule</th><?php endif; ?></tr></thead><tbody><?php $monthNames=[1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre']; $lastPeriod=null; foreach ($group['reports'] as $report): ?><?php $inspectionYear=substr((string)$report['inspection_date'],0,4); $inspectionMonth=(int)substr((string)$report['inspection_date'],5,2); $periodKey=$inspectionYear.'-'.str_pad((string)$inspectionMonth,2,'0',STR_PAD_LEFT); $periodLabel=isset($monthNames[$inspectionMonth])?$monthNames[$inspectionMonth].' '.$inspectionYear:'Date non renseignée'; $showPeriod=$periodKey!==$lastPeriod; $lastPeriod=$periodKey; $transportMeta=json_decode((string)($report['metadata_json']??''),true);$transportMeta=is_array($transportMeta)?$transportMeta:[];$authorizationStatus=$transportGroup?transport_authorization_status($report):null;$expired=$transportGroup&&$authorizationStatus['expired']; ?><?php if($showPeriod): ?><tr class="report-period-heading"><th colspan="<?=$transportGroup?11:9?>"><?=e(ucfirst($periodLabel))?></th></tr><?php endif; ?><tr class="control-report-row<?=$expired?' report-expired':''?>"><td><?=e(date('d/m/Y', strtotime($report['inspection_date'])))?></td><td><?=e($types[$report['inspection_type']]['label'] ?? $report['inspection_type'])?></td><td><?=e($report['region_name'])?></td><td><?=e($report['province_name'])?></td><td<?=$transportGroup?' class="registration-cell"':''?>><?php if($report['inspection_type']==='transport'): ?><?=e(moroccan_registration_display((string)($transportMeta['registration']??''))?:'—')?><?php else: ?><?=e($report['unit_name'])?><?php endif; ?></td><td><?=e($report['authorization_number'])?></td><td><?=e($report['owner_name'])?></td><td><?=e($report['created_by_name'])?></td><?php if($transportGroup): ?><td><?=e(format_date_fr($authorizationStatus['expiration_date']))?><?php if($expired): ?><br><span class="vehicle-expired-badge">Inactif</span><?php endif; ?></td><?php endif; ?><td><a class="btn secondary" href="control_report.php?id=<?=(int)$report['id']?>">Ouvrir / imprimer</a><?php if (($user['role'] ?? '') === ROLE_ADMINISTRATOR): ?><form method="post" action="delete_control.php" class="control-row-delete" onsubmit="return confirm('Supprimer définitivement cette fiche de contrôle ?');"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$report['id']?>"><button class="btn danger btn-sm" type="submit">Supprimer</button></form><?php endif; ?></td><?php if($transportGroup): ?><td><div class="photo-actions"><a class="btn secondary" href="control_photos.php?id=<?=(int)$report['id']?>">Visualiser</a><?php if(can_manage_controls($user)): ?><a class="btn" href="control_form.php?id=<?=(int)$report['id']?>#vehicle-photos">Ajouter</a><?php endif; ?></div></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section>
<?php endforeach; ?>
<?=pagination_html($controlView === 'vehicles' ? 'Pagination des rapports véhicules' : 'Pagination des rapports unités','page',$currentPage,$totalReports,$perPage,'rapport(s)')?><?php endif; ?></div></main>
<footer class="copyright-footer controls-list-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer><script src="assets/idle-timeout.js" defer></script></body></html>
