<?php
declare(strict_types=1);
require_once __DIR__.'/controls_domain.php';
$types=control_checklists();
$template=$types['transport'];
$metadata=[
    'address'=>'Zone industrielle, secteur avicole, région de Rabat-Salé-Kénitra',
    'vehicle_brand'=>'Iveco Daily',
    'vehicle_kind'=>'Camion frigorifique de transport avicole',
    'registration'=>'12345-A-01',
    'chassis'=>'CHASSIS-2026-0123456789',
    'products'=>'live_poultry',
];
$answers=[];
foreach(array_keys($template['items']) as $i=>$key){
    $answers[$key]=['status'=>$i%2?'non':'oui','observation'=>'Vérifié sur le véhicule et conforme aux exigences de transport avicole.'];
}
$report=[
    'id'=>9001,'type'=>'transport','inspection_date'=>'2026-10-29',
    'commission_label'=>'Commission provinciale de contrôle sanitaire avicole',
    'authorization_number'=>'AUT-2026-123456789','owner_name'=>'Société de transport avicole et responsable du véhicule',
    'unit_name'=>'','metadata'=>$metadata,
    'conclusion'=>'Après examen du véhicule, des équipements et des conditions de transport, la commission consigne les observations ci-dessus et demande le maintien des mesures de biosécurité applicables.',
];
$controllers=[
    ['name'=>'Abdelaziz El Amrani','function'=>'Chef de service provincial','signature_text'=>'Signature'],
    ['name'=>'Fatima Zahra Benali','function'=>'Vétérinaire inspectrice','signature_text'=>'Signature'],
    ['name'=>'Mohamed Ait Lahcen','function'=>'Agent de contrôle sanitaire','signature_text'=>'Signature'],
];
echo control_report_html($report,$answers,$controllers,true);
