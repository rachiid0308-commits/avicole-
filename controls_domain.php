<?php
declare(strict_types=1);

/** Checklist content transcribed from the supplied French Word inspection model. */
function control_checklists(): array
{
    $commonFarmFields = [
        'inspection_date' => ['Date du contrôle', 'date'],
        'commission_label' => ['Commission / service de contrôle', 'text'],
        'unit_name' => ['Nom de l’unité', 'readonly'],
        'authorization_number' => ['N° d’autorisation', 'readonly'],
        'society_name' => ['Société / propriétaire', 'readonly'],
        'owner_name' => ['Propriétaire', 'owner'],
        'responsible' => ['Responsable présent', 'text'],
        'address' => ['Adresse', 'text'],
        'authorized_buildings' => ['Bâtiments autorisés', 'number'],
        'total_buildings' => ['Nombre total de bâtiments', 'number'],
        'authorized_capacity' => ['Capacité autorisée', 'text'],
        'headcount' => ['Effectif lors de la visite', 'number'],
        'annexes' => ['Locaux annexes', 'text'],
    ];
    $biosecurity = [
        'gate' => 'Portail empêchant l’entrée des animaux',
        'fence' => 'Clôture (hauteur supérieure à 1,5 m)',
        'wheel_bath' => 'Autoluve à l’entrée',
        'footbath' => 'Pédiluve aux entrées',
        'sanitary_entry' => 'Sas à l’entrée : douche et toilettes avec lavabo',
        'waste_water' => 'Système d’évacuation des eaux usées',
        'carcass_disposal' => 'Destruction des cadavres (fosse à fermeture étanche)',
        'compound_feed' => 'Aliment composé utilisé',
        'water_supply' => 'Alimentation en eau de l’unité',
    ];
    $buildings = [
        'construction_material' => 'Matériaux imputrescibles, faciles à nettoyer, laver et désinfecter',
        'concrete_floor' => 'Sol dallé en béton et lisse',
        'side_walls' => 'Murs latéraux conformes',
        'gable_walls' => 'Pignons en dur',
        'roof_insulation' => 'Toiture dure avec isolation',
        'service_airlock' => 'Sas de service',
        'concrete_surroundings' => 'Abords bétonnés (largeur supérieure à 50 cm)',
        'loading_area' => 'Aire d’enlèvement dallée pour chaque bâtiment',
        'screened_windows' => 'Fenêtres et lanterneaux grillagés',
        'wash_water' => 'Système d’évacuation des eaux de lavage',
    ];
    $manureAndRecords = [
        'egg_equipment' => 'Matériel d’enlèvement des œufs (caisses, cartons…)',
        'egg_storage' => 'Local de stockage des œufs à l’extérieur des bâtiments',
        'manure_area' => 'Aire de stockage des fientes',
        'manure_removal' => 'Évacuation régulière des fientes par camions étanches',
        'manure_spreading' => 'Lieu d’épandage à plus de 500 m de tout élevage avicole',
        'pest_control' => 'Programme de lutte contre les rongeurs et les insectes',
        'cleaning_plan' => 'Plan de nettoyage et de désinfection (produits légaux)',
        'sanitary_gap' => 'Vide sanitaire pratiqué selon les exigences du modèle',
        'health_register' => 'Registre sanitaire tenu à jour et visé par le vétérinaire',
        'other_animals' => 'Absence d’animaux ou espèces non autorisés',
        'staff_housing' => 'Logement du personnel isolé avec accès propre depuis l’extérieur',
    ];

    return [
        'chair' => [
            'label' => 'Chair',
            'title' => 'CHECK-LIST DU CONTRÔLE DES UNITÉS D’ÉLEVAGE AVICOLE DE TYPE CHAIR',
            'fields' => $commonFarmFields,
            'questions' => [],
            'items' => [
                'fence' => 'Clôture', 'wheel_bath' => 'Autoluve', 'footbath' => 'Pédiluve',
                'showers' => 'Sas et douches (emplacement et conception)', 'wc' => 'WC (emplacement et lavabo)',
                'windows' => 'Lanterneaux / fenêtres grillagés', 'floor_walls' => 'Sol et murs', 'roof' => 'Toiture',
                'service_airlock' => 'Sas de service', 'insulation' => 'Isolation', 'wash_water' => 'Évacuation des eaux de lavage',
                'reform_area' => 'Aire de réforme dallée', 'concrete_surroundings' => 'Abords des bâtiments bétonnés',
                'carcass_disposal' => 'Moyen de destruction des cadavres', 'cleaning_plan' => 'Plan de désinfection',
                'deratization' => 'Plan de dératisation', 'annexes' => 'Locaux annexes', 'sanitary_gap' => 'Vide sanitaire entre deux bandes successives',
                'single_band' => 'Respect de la bande unique', 'staff_housing' => 'Logement du personnel',
                'register_compliance' => 'Conformité du registre d’élevage', 'register_updated' => 'Registre tenu à jour',
                'delivery_notes' => 'Bons de livraison des aliments et des poussins', 'veterinary_orders' => 'Ordonnances du vétérinaire sanitaire mandaté',
                'veterinary_signature' => 'Signature du vétérinaire encadrant', 'manager_signature' => 'Signature du responsable',
            ],
        ],
        'reproducteurs' => [
            'label' => 'Reproducteurs',
            'title' => 'CHECK-LIST DU CONTRÔLE DES UNITÉS D’ÉLEVAGE AVICOLE DE TYPE REPRODUCTEURS',
            'fields' => $commonFarmFields + [
                'unit_subtype' => ['Type d’unité', 'select:poussiniere,production'],
            ],
            'questions' => [
                'other_sites' => 'Autres élevages avicoles sur d’autres sites (types, nombre et contacts)',
                'feed_factory' => 'Usine d’aliment pour les propres élevages (préciser où)',
            ],
            'items' => $biosecurity + $buildings + $manureAndRecords + [
                'sanitary_gap_20' => 'Vide sanitaire de 20 jours au niveau de chaque ferme',
            ],
        ],
        'pondeuses' => [
            'label' => 'Pondeuses',
            'title' => 'CHECK-LIST DE CONTRÔLE DES UNITÉS DE POULES PONDEUSES',
            'fields' => $commonFarmFields + [
                'exploited_capacity' => ['Capacité exploitée', 'text'],
            ],
            'questions' => [
                'typhosis_program' => 'L’unité adhère-t-elle au programme de lutte contre la typhose aviaire ?',
                'other_sites' => 'Autres élevages avicoles sur d’autres sites (types, nombre et contacts)',
                'feed_factory' => 'Usine d’aliment pour les propres élevages (préciser où)',
            ],
            'items' => $biosecurity + $buildings + [
                'exploited_capacity' => 'Capacité exploitée',
            ] + $manureAndRecords + [
                'pullets_delivery' => 'Présence de bons de livraison de la poulette',
            ],
        ],
        'transport' => [
            'label' => 'Transport',
            'title' => 'FICHE DE CONTRÔLE DE CONFORMITÉ DES MOYENS DE TRANSPORT DES VOLAILLES ET LES ŒUFS À COUVER (OAC)',
            'fields' => [
                'inspection_date' => ['Date du contrôle', 'date'],
                'commission_label' => ['Commission de contrôle', 'text'],
                'authorization_number' => ['Autorisation N° (facultatif)', 'text'],
                'authorization_date' => ['Date d’autorisation', 'date'],
                'owner_name' => ['Société / responsable', 'text'],
                'address' => ['Adresse', 'text'],
                'vehicle_brand' => ['Marque', 'text'],
                'vehicle_kind' => ['Genre', 'text'],
                'registration' => ['Numéro d’immatriculation', 'plate'],
                'chassis' => ['N° du châssis', 'text'],
                'products' => ['Produits à transporter', 'select:poussin,eggs,live_poultry'],
            ],
            'questions' => [],
            'sections' => [
                'common' => ['title' => 'Contrôle commun', 'items' => [
                    'vehicle_washable' => 'Parois et plancher (faciles à laver et à désinfecter)',
                    'authorized_products' => 'Produits de nettoyage et de désinfection utilisés autorisés ou non au Maroc',
                    'same_origin' => 'Transport de volailles / OAC / poussins de même espèce, de même âge, de même type et provenant de la même origine',
                ]],
                'live_poultry' => ['title' => 'Contrôle concernant les volailles vivantes', 'items' => [
                    'crates_washable' => 'Cageots et caisses faciles à laver et à désinfecter (en plastique ou en métal), conçues de manière à ne pas blesser les volailles',
                    'metal_crates' => 'Si les caisses sont en fer, elles sont recouvertes d’une peinture de couleur claire et résistante à l’eau',
                    'inner_walls' => 'Parois internes des caisses (camions…) faciles à laver et à désinfecter ; si elles sont en bois, elles sont recouvertes d’une peinture de couleur claire et résistante à l’eau',
                    'platform' => 'Plateforme des caisses en matériaux imputrescibles et faciles à laver et à désinfecter',
                    'no_spread' => 'Absence de possibilité de dissémination des fientes et des plumes',
                    'ventilation' => 'Aération et visibilité des volailles transportées',
                ]],
                'poussin' => ['title' => 'Contrôle concernant les poussins', 'items' => [
                    'chick_packaging' => 'Emballages à usage unique ou à réemploi (faciles à laver et à désinfecter)',
                    'thermal_insulation' => 'Engins bien isolés thermiquement et équipés de ventilation',
                    'air_conditioning' => 'Dispositif de conditionnement d’air',
                ]],
                'eggs' => ['title' => 'Contrôle concernant les œufs à couver (OAC)', 'items' => [
                    'oac_packaging' => 'Emballages à usage unique ou à réemploi (faciles à laver et à désinfecter)',
                    'oac_temperature' => 'Température de transport entre 15 et 17 °C et humidité relative de 70 % ± 2 %',
                ]],
            ],
            'items' => [
                'vehicle_washable' => 'Parois et plancher (faciles à laver et à désinfecter)',
                'authorized_products' => 'Produits de nettoyage et de désinfection utilisés autorisés ou non au Maroc',
                'same_origin' => 'Transport de volailles / OAC / poussins de même espèce, de même âge, de même type et provenant de la même origine',
                'crates_washable' => 'Cageots et caisses faciles à laver et à désinfecter (en plastique ou en métal), conçues de manière à ne pas blesser les volailles',
                'metal_crates' => 'Si les caisses sont en fer, elles sont recouvertes d’une peinture de couleur claire et résistante à l’eau',
                'inner_walls' => 'Parois internes des caisses (camions…) faciles à laver et à désinfecter ; si elles sont en bois, elles sont recouvertes d’une peinture de couleur claire et résistante à l’eau',
                'platform' => 'Plateforme des caisses en matériaux imputrescibles et faciles à laver et à désinfecter',
                'no_spread' => 'Absence de possibilité de dissémination des fientes et des plumes',
                'ventilation' => 'Aération et visibilité des volailles transportées',
                'chick_packaging' => 'Emballages à usage unique ou à réemploi (faciles à laver et à désinfecter)',
                'thermal_insulation' => 'Engins bien isolés thermiquement et équipés de ventilation',
                'air_conditioning' => 'Dispositif de conditionnement d’air',
                'oac_packaging' => 'Emballages à usage unique ou à réemploi (faciles à laver et à désinfecter)',
                'oac_temperature' => 'Température de transport entre 15 et 17 °C et humidité relative de 70 % ± 2 %',
            ],
        ],
    ];
}

function can_manage_controls(array $user): bool
{
    return ($user['role'] ?? '') === ROLE_ADMINISTRATOR || in_array(($user['role'] ?? ''), [ROLE_PROVINCIAL, ROLE_VETERINARY_RESPONSIBLE], true);
}

function require_control_writer(array $user): void
{
    if (!can_manage_controls($user)) {
        http_response_code(403);
        exit('Seul le Chef de service, le Vétérinaire ou l’Administrateur peut créer, modifier ou supprimer un contrôle.');
    }
}

function transport_product_label(string $value): string
{
    return [
        'poussin' => 'Poussins',
        'eggs' => 'Œufs à couver (OAC)',
        'live_poultry' => 'Volailles vivantes',
    ][$value] ?? '';
}

function transport_authorization_status(array $report, ?DateTimeImmutable $today = null): array
{
    $metadata = $report['metadata'] ?? $report['metadata_json'] ?? [];
    if (is_string($metadata)) {
        $decoded = json_decode($metadata, true);
        $metadata = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($metadata)) $metadata = [];
    $authorizationDate = trim((string)($metadata['authorization_date'] ?? ''));
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $authorizationDate)) {
        $authorizationDate = trim((string)($report['inspection_date'] ?? ''));
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $authorizationDate);
    if (!$parsed || $parsed->format('Y-m-d') !== $authorizationDate) {
        return ['authorization_date' => null, 'expiration_date' => null, 'expired' => false];
    }
    $expiration = add_calendar_months($parsed, 12);
    $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
    return [
        'authorization_date' => $parsed->format('Y-m-d'),
        'expiration_date' => $expiration->format('Y-m-d'),
        'expired' => $today > $expiration->setTime(0, 0),
    ];
}

function moroccan_registration_letters(): array
{
    return ['A', 'B', 'D', 'H', 'E', 'T', 'Y', 'K', 'L', 'M', 'N'];
}

function moroccan_registration_latin_letter(string $letter): string
{
    if (in_array($letter, moroccan_registration_letters(), true)) return $letter;
    $legacyArabic = [
        'أ' => 'A', 'ب' => 'B', 'د' => 'D', 'هـ' => 'H', 'و' => 'E', 'ط' => 'T',
        'ي' => 'Y', 'ك' => 'K', 'ل' => 'L', 'م' => 'M', 'ن' => 'N',
    ];
    return $legacyArabic[$letter] ?? $letter;
}

function moroccan_registration_display(string $registration): string
{
    $parts = explode('-', $registration);
    if (count($parts) !== 3) return $registration;
    $letter = moroccan_registration_latin_letter($parts[1]);
    if (!in_array($letter, moroccan_registration_letters(), true)) return $registration;
    return $parts[0] . '-' . $letter . '-' . $parts[2];
}

function valid_moroccan_registration(string $serial, string $letter, string $code): bool
{
    return preg_match('/^\\d{5}$/', $serial) === 1
        && in_array($letter, moroccan_registration_letters(), true)
        && preg_match('/^(0[1-9]|[1-8][0-9])$/', $code) === 1;
}

function control_mark(?string $response, string $column): string
{
    return $response === $column ? 'X' : '';
}

function vehicle_photo_views(): array
{
    return ['front' => 'Face avant', 'rear' => 'Face arrière', 'right' => 'Côté droit', 'left' => 'Côté gauche'];
}

/** Validate uploaded images from the four fixed vehicle views before storing binary data. */
function control_vehicle_photo_uploads(array $upload): array
{
    $views = vehicle_photo_views();
    if (!isset($upload['error']) || !is_array($upload['error'])) return [];
    $photos = [];
    $totalBytes = 0;
    $maxPhotoBytes = 2 * 1024 * 1024;
    $maxTotalBytes = 6 * 1024 * 1024;
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    if (!$finfo) throw new RuntimeException('La validation des photos nécessite l’extension PHP Fileinfo.');
    foreach ($views as $view => $_label) {
        $error = (int)($upload['error'][$view] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Le chargement de la photo « ' . $_label . ' » a échoué. Réduisez sa taille puis réessayez.');
        $tmp = (string)($upload['tmp_name'][$view] ?? '');
        $reportedSize = (int)($upload['size'][$view] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new InvalidArgumentException('Fichier photo invalide pour « ' . $_label . ' ».');
        if ($reportedSize < 1 || $reportedSize > $maxPhotoBytes) throw new InvalidArgumentException('Chaque photo doit faire au maximum 2 Mo.');
        $bytes = file_get_contents($tmp);
        if (!is_string($bytes) || strlen($bytes) !== $reportedSize) throw new InvalidArgumentException('Impossible de lire la photo « ' . $_label . ' ».');
        $totalBytes += strlen($bytes);
        if ($totalBytes > $maxTotalBytes) throw new InvalidArgumentException('La taille totale des photos ne doit pas dépasser 6 Mo.');
        $mime = $finfo->buffer($bytes);
        if (!is_string($mime) || !in_array($mime, $allowedMimes, true)) throw new InvalidArgumentException('Format photo non accepté pour « ' . $_label . ' » (JPEG, PNG ou WebP seulement).');
        $dimensions = function_exists('getimagesizefromstring') ? @getimagesizefromstring($bytes) : false;
        if (!is_array($dimensions) || (int)$dimensions[0] < 1 || (int)$dimensions[1] < 1 || (int)$dimensions[0] > 8000 || (int)$dimensions[1] > 8000 || (int)$dimensions[0] * (int)$dimensions[1] > 30000000) {
            throw new InvalidArgumentException('La photo « ' . $_label . ' » est illisible ou ses dimensions sont trop grandes.');
        }
        $photos[$view] = ['mime_type' => $mime, 'image_data' => $bytes];
    }
    return $photos;
}

function control_unit_photo_uploads(array $upload): array
{
    if (!isset($upload['error']) || !is_array($upload['error'])) return [];
    $photos=[]; $totalBytes=0; $maxPhotoBytes=2*1024*1024; $maxTotalBytes=8*1024*1024;
    $allowedMimes=['image/jpeg','image/png','image/webp'];
    $finfo=class_exists('finfo')?new finfo(FILEINFO_MIME_TYPE):null;
    if (!$finfo) throw new RuntimeException('La validation des photos nécessite l’extension PHP Fileinfo.');
    foreach ($upload['error'] as $slot=>$uploadError) {
        $slot=(int)$slot; $error=(int)$uploadError;
        if ($error===UPLOAD_ERR_NO_FILE) continue;
        if ($slot<1 || $slot>6) throw new InvalidArgumentException('Un rapport de visite accepte six photos au maximum.');
        if ($error!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Le chargement de la photo '.$slot.' a échoué. Réduisez sa taille puis réessayez.');
        $tmp=(string)($upload['tmp_name'][$slot]??''); $reportedSize=(int)($upload['size'][$slot]??0);
        if ($tmp==='' || !is_uploaded_file($tmp)) throw new InvalidArgumentException('Fichier photo invalide pour la photo '.$slot.'.');
        if ($reportedSize<1 || $reportedSize>$maxPhotoBytes) throw new InvalidArgumentException('Chaque photo de visite doit faire au maximum 2 Mo.');
        $bytes=file_get_contents($tmp);
        if (!is_string($bytes) || strlen($bytes)!==$reportedSize) throw new InvalidArgumentException('Impossible de lire la photo '.$slot.'.');
        $totalBytes+=strlen($bytes);
        if ($totalBytes>$maxTotalBytes) throw new InvalidArgumentException('La taille totale des photos de visite ne doit pas dépasser 8 Mo.');
        $mime=$finfo->buffer($bytes);
        if (!is_string($mime) || !in_array($mime,$allowedMimes,true)) throw new InvalidArgumentException('Format photo non accepté (JPEG, PNG ou WebP seulement).');
        $dimensions=function_exists('getimagesizefromstring')?@getimagesizefromstring($bytes):false;
        if (!is_array($dimensions)||(int)$dimensions[0]<1||(int)$dimensions[1]<1||(int)$dimensions[0]>8000||(int)$dimensions[1]>8000||(int)$dimensions[0]*(int)$dimensions[1]>30000000) throw new InvalidArgumentException('Une photo de visite est illisible ou ses dimensions sont trop grandes.');
        $photos[$slot]=['mime_type'=>$mime,'image_data'=>$bytes];
    }
    return $photos;
}

function photo_slot_deletions(mixed $input, int $maximum=6): array
{
    if (!is_array($input)) return [];
    $slots=[];
    foreach ($input as $value) {
        $slot=filter_var($value,FILTER_VALIDATE_INT);
        if ($slot===false || $slot<1 || $slot>$maximum) throw new InvalidArgumentException('Emplacement de photo invalide.');
        $slots[$slot]=$slot;
    }
    return array_values($slots);
}

function vehicle_photo_deletions(mixed $input): array
{
    if (!is_array($input)) return [];
    $allowed=vehicle_photo_views(); $views=[];
    foreach ($input as $view) {
        if (!is_string($view)||!isset($allowed[$view])) throw new InvalidArgumentException('Vue de photo de véhicule invalide.');
        $views[$view]=$view;
    }
    return array_values($views);
}

function persist_control_vehicle_photos(PDO $pdo, int $reportId, array $photos, array $deletions=[]): void
{
    $allowed=vehicle_photo_views();
    $delete=$pdo->prepare('DELETE FROM control_report_photos WHERE report_id=:report_id AND view_key=:view_key');
    foreach ($deletions as $view) if (isset($allowed[$view])) $delete->execute([':report_id'=>$reportId,':view_key'=>$view]);
    $insert=$pdo->prepare('INSERT INTO control_report_photos (report_id,view_key,mime_type,image_data) VALUES (:report_id,:view_key,:mime_type,:image_data)');
    foreach ($photos as $view=>$photo) {
        if (!isset($allowed[$view])||!is_array($photo)||!in_array($photo['mime_type']??'', ['image/jpeg','image/png','image/webp'], true)||!is_string($photo['image_data']??null)) continue;
        $delete->execute([':report_id'=>$reportId,':view_key'=>$view]);
        $insert->bindValue(':report_id',$reportId,PDO::PARAM_INT); $insert->bindValue(':view_key',$view,PDO::PARAM_STR);
        $insert->bindValue(':mime_type',$photo['mime_type'],PDO::PARAM_STR); $insert->bindValue(':image_data',$photo['image_data'],PDO::PARAM_LOB); $insert->execute();
    }
}

function persist_control_unit_photos(PDO $pdo, int $reportId, array $photos, array $deletions=[]): void
{
    $delete=$pdo->prepare('DELETE FROM control_report_unit_photos WHERE report_id=:report_id AND photo_slot=:slot');
    foreach ($deletions as $slot) if (is_int($slot)&&$slot>=1&&$slot<=6) $delete->execute([':report_id'=>$reportId,':slot'=>$slot]);
    $insert=$pdo->prepare('INSERT INTO control_report_unit_photos (report_id,photo_slot,mime_type,image_data) VALUES (:report_id,:slot,:mime_type,:image_data)');
    foreach ($photos as $slot=>$photo) {
        $slot=(int)$slot;
        if ($slot<1||$slot>6||!is_array($photo)||!in_array($photo['mime_type']??'', ['image/jpeg','image/png','image/webp'], true)||!is_string($photo['image_data']??null)) continue;
        $delete->execute([':report_id'=>$reportId,':slot'=>$slot]);
        $insert->bindValue(':report_id',$reportId,PDO::PARAM_INT); $insert->bindValue(':slot',$slot,PDO::PARAM_INT);
        $insert->bindValue(':mime_type',$photo['mime_type'],PDO::PARAM_STR); $insert->bindValue(':image_data',$photo['image_data'],PDO::PARAM_LOB); $insert->execute();
    }
}

function control_report_html(array $report, array $answers, array $controllers, bool $word = false): string
{
    $types = control_checklists();
    $type = (string)($report['type'] ?? '');
    if (!isset($types[$type])) throw new InvalidArgumentException('Type de contrôle invalide.');
    $template = $types[$type];
    $meta = $report['metadata'] ?? [];
    if (is_string($meta)) {
        $decoded = json_decode($meta, true);
        $meta = is_array($decoded) ? $decoded : [];
    }
    $h = static fn(mixed $value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $date = (string)($report['inspection_date'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = substr($date, 8, 2) . '/' . substr($date, 5, 2) . '/' . substr($date, 0, 4);
    $wordHead = $word ? '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]--><meta name="ProgId" content="Word.Document"><meta name="Generator" content="Microsoft Word">' : '';
    $title = $h($template['title']);
    $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>' . $title . '</title>' . $wordHead . '<style>'
        . 'body{font-family:Arial,sans-serif;color:#17211c;font-size:10pt;margin:20px auto;max-width:1000px}h1{font-size:16pt;text-align:center;border-bottom:2px solid #244d3c;padding-bottom:8px}h2{font-size:12pt;margin-top:22px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:7px 20px;margin:16px 0}.meta div{border-bottom:1px solid #bbb;padding:4px 2px}.meta b{color:#34493d}table{width:100%;border-collapse:collapse;margin:12px 0;table-layout:auto}th,td{border:1px solid #66736b;padding:5px 6px;vertical-align:top;overflow-wrap:anywhere;word-break:normal}th{background:#e8eee9}.checklist th:first-child,.checklist td:first-child{width:54%}.checklist th:nth-child(2),.checklist th:nth-child(3),.checklist td.mark{width:7%;text-align:center}.checklist th:last-child,.checklist td:last-child{width:32%}td.mark{font-weight:bold;font-size:12pt}.observation{white-space:pre-wrap;min-width:120px}.section-heading th{text-align:left;background:#dce9df}.inactive-section th,.inactive-criterion td{color:#69736c;background:#eff1ef}.controllers td{height:28px}.conclusion{white-space:pre-wrap;min-height:70px;border:1px solid #66736b;padding:10px}.toolbar{margin:12px 0;display:flex;gap:8px}.toolbar a,.toolbar button{padding:8px 12px;border:0;border-radius:4px;background:#245b43;color:white;text-decoration:none;cursor:pointer}@media screen{body{width:min(1600px,calc(100% - 64px));max-width:none;margin:24px auto}.meta{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px 16px;margin:12px 0}h1{font-size:20pt;padding-bottom:6px}h2{margin-top:16px}.conclusion{min-height:0;padding:8px}.toolbar{margin:10px 0}}@media screen and (max-width:760px){body{width:auto;max-width:none;margin:12px}.meta{grid-template-columns:repeat(2,minmax(0,1fr));gap:5px 10px}h1{font-size:15pt}h2{font-size:11pt;margin-top:12px}.toolbar{flex-wrap:wrap}}@media screen and (max-width:480px){.meta{grid-template-columns:1fr}}@page{size:A4 portrait;margin:2mm}@media print{html,body{width:auto;min-width:0;max-width:none;margin:0;padding:0;font-size:7pt;line-height:1.02;zoom:1}h1{font-size:11pt;line-height:1.08;margin:0 0 1mm;padding-bottom:1mm;overflow-wrap:anywhere}h2{font-size:8pt;line-height:1.05;margin:1mm 0 .5mm}.toolbar{display:none}.meta{grid-template-columns:repeat(3,minmax(0,1fr));gap:.6mm 2mm;margin:1mm 0}.meta div{padding:.45mm 0;line-height:1.02;overflow-wrap:anywhere}.meta-subtitle{grid-column:1/-1}.checklist-heading{break-before:auto;page-break-before:auto;margin:0 0 .5mm;padding:.6mm 1.5mm;font-size:7pt;line-height:1.02}.checklist{width:100%;table-layout:fixed;font-size:6.6pt;line-height:1;margin:.5mm 0;break-inside:auto;page-break-inside:auto}.checklist th,.checklist td{padding:1px 2px;line-height:1.03;overflow-wrap:anywhere;word-break:break-word}.checklist th:first-child,.checklist td:first-child{width:50%}.checklist th:nth-child(2),.checklist th:nth-child(3),.checklist td.mark{width:5%;text-align:center}.checklist th:last-child,.checklist td:last-child{width:40%}.checklist td.mark{font-size:7.2pt}.checklist .observation{min-width:0;white-space:pre-wrap}.section-heading th{font-size:7pt;padding:.5mm 2px}.conclusion{min-height:0;padding:1mm 1.5mm;line-height:1.03;overflow-wrap:anywhere}.controllers th,.controllers td{padding:1px 2px;line-height:1.02;overflow-wrap:anywhere}.controllers td{height:4mm}table{width:100%;margin:.6mm 0;table-layout:fixed;break-inside:auto}thead{display:table-header-group}td,th{overflow-wrap:anywhere;word-break:break-word}h1,h2{break-after:avoid;page-break-after:avoid}tr{break-inside:avoid;page-break-inside:avoid}}@media screen and (max-width:640px){body{width:auto;margin:0;padding:12px;max-width:none;font-size:14px;line-height:1.45;zoom:1}h1{font-size:19px;text-align:left;margin:0 0 12px;line-height:1.25}h2{font-size:16px;margin:16px 0 8px}.meta{grid-template-columns:minmax(0,1fr);gap:6px;margin:10px 0}.toolbar{position:sticky;top:0;z-index:10;flex-wrap:wrap;padding:8px;background:#fff}.toolbar a,.toolbar button{flex:1 1 100%;min-height:44px;text-align:center}.checklist-scroll{width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}.checklist{display:table;width:640px;min-width:640px;max-width:none}.controllers{width:100%;min-width:0;table-layout:fixed}.controllers th,.controllers td{overflow-wrap:anywhere;word-break:break-word}.conclusion{min-height:60px}}' . '</style></head><body>';
    $printCss = '<style>@page{size:A4 portrait;margin:8mm 5mm}@media print{html{print-color-adjust:exact;-webkit-print-color-adjust:exact}html,body{width:auto!important;min-width:0!important;max-width:none!important;margin:0!important;padding:0!important;color:#172b24!important;font-family:Arial,sans-serif!important;font-size:8pt!important;line-height:1.16!important}.print-brand{display:flex!important;align-items:center;gap:3mm;margin:0 0 3mm;padding:0 0 2mm;border-bottom:1.2pt solid #245b43}.print-brand img{width:16mm;height:12mm;object-fit:contain}.print-brand-copy{display:flex;flex-direction:column;gap:.5mm}.print-brand-copy strong{color:#174b35;font-size:9pt;letter-spacing:.04em}.print-brand-copy span{color:#52665a;font-size:6.8pt}.print-ref{margin-left:auto;color:#52665a;font-size:7pt;text-align:right}h1{margin:0 0 2mm!important;padding:0 0 1.5mm!important;border-bottom:1pt solid #aabbb0!important;color:#173d2d;font-size:13pt!important;line-height:1.12!important;text-align:left!important}h2{margin:2.5mm 0 1mm!important;color:#244d3c;font-size:9pt!important;line-height:1.12!important}.toolbar{display:none!important}.meta{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:1mm 3mm!important;margin:2mm 0!important;padding:2mm 2.5mm;background:#f4f7f5;border:1px solid #cad6ce;border-radius:1mm}.meta div{padding:1mm 0!important;border-bottom:1px solid #d4ddd7!important;line-height:1.15!important;overflow-wrap:anywhere}.meta-subtitle{grid-column:1/-1;font-weight:700;color:#244d3c}.checklist-heading{margin:2mm 0 1mm!important;padding:1.2mm 2mm!important;border-left:2mm solid #287451;background:#eef4f0;color:#183d2e;font-size:9pt!important}.checklist-scroll{width:100%!important;max-width:none!important;overflow:visible!important}.checklist,table{width:100%!important;max-width:none!important;border-collapse:collapse!important;table-layout:fixed!important;margin:1mm 0!important;break-inside:auto;page-break-inside:auto;font-size:7.6pt!important;line-height:1.14!important}.checklist th:first-child,.checklist td:first-child{width:49%!important}.checklist th:nth-child(2),.checklist th:nth-child(3),.checklist td.mark{width:6%!important;text-align:center}.checklist th:last-child,.checklist td:last-child{width:39%!important}.checklist th,.checklist td,table th,table td{padding:1.5mm 1.7mm!important;border:.6pt solid #67766d!important;vertical-align:top;overflow-wrap:anywhere;word-break:normal}.checklist thead th,.controllers thead th{background:#e6eee8!important;color:#1f4936!important;font-weight:700}.checklist .section-heading th{padding:1mm 1.7mm!important;background:#dce9df!important;text-align:left}.inactive-section th,.inactive-criterion td{background:#f0f2f0!important;color:#59655d!important}.checklist td.mark{font-size:9pt!important;font-weight:700}.observation{min-width:0!important;white-space:pre-wrap;overflow-wrap:anywhere}.conclusion{min-height:12mm!important;padding:2mm 2.5mm!important;border:1px solid #67766d!important;background:#fbfcfb;white-space:pre-wrap;overflow-wrap:anywhere}.controllers{table-layout:fixed!important}.controllers th:first-child,.controllers td:first-child{width:36%}.controllers th:nth-child(2),.controllers td:nth-child(2){width:28%}.controllers th:last-child,.controllers td:last-child{width:36%}.controllers td{height:7mm!important}.controllers th,.controllers td{padding:1.5mm 1.7mm!important}.checklist thead{display:table-header-group}tr{break-inside:avoid;page-break-inside:avoid}h1,h2{break-after:avoid;page-break-after:avoid}.print-brand,.meta,.conclusion{break-inside:avoid;page-break-inside:avoid}}.print-brand{display:none}</style>';
    $html = str_replace('</head>', $printCss . '</head>', $html);
    $reportListPage = $type === 'transport' ? 'vehicle_controls.php' : 'controls.php';
    if (!$word) $html .= '<div class="toolbar"><button onclick="window.print()" type="button">Imprimer le rapport</button><a href="?id=' . (int)($report['id'] ?? 0) . '&format=word">Télécharger le rapport Word</a><a href="' . $reportListPage . '">Retour aux rapports</a></div>';
    if (!$word) $html .= '<div class="print-brand"><div class="print-ref">Rapport N° ' . (int)($report['id'] ?? 0) . '<br>Édité le ' . $h(date('d/m/Y')) . '</div></div>';
    $html .= '<h1>' . $title . '</h1><div class="meta">';
    if ($type === 'transport') {
        $html .= '<div><b>Date :</b> ' . $h($date) . '</div>'
            . '<div><b>Commission de contrôle :</b> ' . $h($report['commission_label'] ?? '') . '</div>'
            . '<div><b>Autorisation N° :</b> ' . $h($report['authorization_number'] ?? '') . '</div>'
            . '<div><b>Société / responsable :</b> ' . $h($report['owner_name'] ?? '') . '</div>'
            . '<div><b>Adresse :</b> ' . $h($meta['address'] ?? '') . '</div>'
            . '<div class="meta-subtitle"><b>Renseignements sur le véhicule</b></div>';
        foreach ($template['fields'] as $key => [$label, $kind]) {
            if (in_array($key, ['inspection_date', 'commission_label', 'authorization_number', 'owner_name', 'responsible', 'address'], true)) continue;
            $value = $meta[$key] ?? '';
            if ($key === 'products') $value = transport_product_label((string)$value);
            elseif ($key === 'registration') $value = moroccan_registration_display((string)$value);
            elseif ($key === 'authorization_date' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', (string)$value)) $value = substr((string)$value, 8, 2) . '/' . substr((string)$value, 5, 2) . '/' . substr((string)$value, 0, 4);
            if ((string)$value !== '') $html .= '<div><b>' . $h($label) . ' :</b> ' . $h($value) . '</div>';
        }
    } else {
        $html .= '<div><b>Date du contrôle :</b> ' . $h($date) . '</div>'
            . '<div><b>Commission de contrôle :</b> ' . $h($report['commission_label'] ?? '') . '</div>'
            . '<div><b>Région :</b> ' . $h($report['region_name'] ?? '') . '</div>'
            . '<div><b>Province / préfecture :</b> ' . $h($report['province_name'] ?? '') . '</div>'
            . '<div><b>Unité :</b> ' . $h($report['unit_name'] ?? '') . '</div>'
            . '<div><b>N° d’autorisation :</b> ' . $h($report['authorization_number'] ?? '') . '</div>'
            . '<div><b>Société / propriétaire :</b> ' . $h($report['owner_name'] ?? '') . '</div>'
            . '<div><b>Responsable :</b> ' . $h($meta['responsible'] ?? '') . '</div>'
            . '<div><b>Adresse :</b> ' . $h($meta['address'] ?? '') . '</div>';
        foreach ($template['fields'] as $key => [$label, $kind]) {
            if (in_array($key, ['inspection_date', 'commission_label', 'unit_name', 'authorization_number', 'society_name', 'owner_name', 'responsible', 'address'], true)) continue;
            $value = $meta[$key] ?? '';
            if ($kind === 'select:poussiniere,production') $value = ['poussiniere' => 'Poussinière', 'production' => 'Production'][$value] ?? $value;
            if ((string)$value !== '') $html .= '<div><b>' . $h($label) . ' :</b> ' . $h($value) . '</div>';
        }
    }
    $html .= '</div><h2 class="checklist-heading">Éléments à contrôler</h2><div class="checklist-scroll"><table class="checklist"><thead><tr><th>Éléments à contrôler</th><th>Oui</th><th>Non</th><th>Observations</th></tr></thead><tbody>';
    $renderAnswerRow = static function (string $key, string $label, bool $inactive = false) use (&$html, $answers, $h): void {
        $answer = $inactive ? [] : ($answers[$key] ?? []);
        if (is_object($answer)) $answer = (array)$answer;
        $status = is_array($answer) ? (string)($answer['status'] ?? '') : '';
        $observation = is_array($answer) ? (string)($answer['observation'] ?? '') : '';
        $rowClass = $inactive ? ' class="inactive-criterion"' : '';
        $html .= '<tr' . $rowClass . '><td>' . $h($label) . '</td><td class="mark">' . control_mark($status, 'oui') . '</td><td class="mark">' . control_mark($status, 'non') . '</td><td class="observation">' . nl2br($h($observation)) . '</td></tr>';
    };
    if ($type === 'transport') {
        $product = (string)($meta['products'] ?? '');
        $activeSection = ['live_poultry' => 'live_poultry', 'poussin' => 'poussin', 'eggs' => 'eggs'][$product] ?? '';
        foreach ($template['sections'] as $sectionKey => $section) {
            $active = $sectionKey === 'common' || $sectionKey === $activeSection;
            $inactiveClass = $active ? '' : ' inactive-section';
            $inactiveLabel = $active ? '' : ' — Non applicable au produit choisi';
            $html .= '<tr class="section-heading' . $inactiveClass . '"><th colspan="4">' . $h($section['title'] . $inactiveLabel) . '</th></tr>';
            foreach ($section['items'] as $key => $label) $renderAnswerRow($key, $label, !$active);
        }
    } else {
        foreach ($template['items'] as $key => $label) $renderAnswerRow($key, $label);
    }
    $html .= '</tbody></table></div>';
    if ($template['questions']) {
        $html .= '<h2>Questions complémentaires</h2><table><tbody>';
        foreach ($template['questions'] as $key => $label) $html .= '<tr><th>' . $h($label) . '</th><td class="observation">' . nl2br($h($meta[$key] ?? '')) . '</td></tr>';
        $html .= '</tbody></table>';
    }
    $html .= '<h2>Conclusion de la commission</h2><div class="conclusion">' . nl2br($h($report['conclusion'] ?? '')) . '</div>';
    $html .= '<h2>Membres de la commission de contrôle</h2><table class="controllers"><thead><tr><th>Nom et prénom</th><th>Fonction</th><th>Signature</th></tr></thead><tbody>';
    if (!$controllers) $controllers = [['name' => '', 'function' => '', 'signature_text' => '']];
    foreach ($controllers as $controller) $html .= '<tr><td>' . $h($controller['name'] ?? '') . '</td><td>' . $h($controller['function'] ?? '') . '</td><td>' . $h($controller['signature_text'] ?? '') . '</td></tr>';
    $html .= '</tbody></table>';
    if (!$word) $html .= '<script>window.addEventListener("afterprint",function(){window.location.href="' . $reportListPage . '";});</script>';
    $html .= '</body></html>';
    return $html;
}

/** Persist a normalized report and its checklist answers/controller signatures atomically. */
function control_create(PDO $pdo, array $report, array $answers, array $controllers, array $photos = [], array $unitPhotos = []): int
{
    $types = control_checklists();
    $type = (string)($report['type'] ?? '');
    if (!isset($types[$type])) throw new InvalidArgumentException('Type de contrôle invalide.');
    $template = $types[$type];
    $allowedItems = $template['items'];
    if ($type === 'transport') {
        $product = (string)($report['metadata']['products'] ?? '');
        if (!in_array($product, ['poussin', 'eggs', 'live_poultry'], true)) throw new InvalidArgumentException('Choisissez un produit à transporter.');
        $visibleKeys = array_keys($template['sections']['common']['items']);
        $productSection = $product === 'poussin' ? 'poussin' : ($product === 'eggs' ? 'eggs' : 'live_poultry');
        $allowedItems = array_intersect_key($allowedItems, array_flip(array_merge($visibleKeys, array_keys($template['sections'][$productSection]['items']))));
        $registration = (string)($report['metadata']['registration'] ?? '');
        $parts = explode('-', $registration);
        if (count($parts) !== 3 || !valid_moroccan_registration($parts[0], $parts[1], $parts[2])) throw new InvalidArgumentException('Le numéro d’immatriculation doit suivre le format XXXXX-lettre-YY.');
    }
    $normalizedAnswers = [];
    foreach ($allowedItems as $key => $_label) {
        $entry = $answers[$key] ?? [];
        if (!is_array($entry)) $entry = [];
        $status = (string)($entry['status'] ?? '');
        if (!in_array($status, ['', 'oui', 'non'], true)) throw new InvalidArgumentException('Réponse de conformité invalide.');
        $observation = trim((string)($entry['observation'] ?? ''));
        if (function_exists('mb_strlen') && mb_strlen($observation, 'UTF-8') > 4000) throw new InvalidArgumentException('Une observation dépasse 4 000 caractères.');
        $normalizedAnswers[$key] = [$status === '' ? null : $status, $observation];
    }
    $normalizedControllers = [];
    foreach ($controllers as $controller) {
        if (!is_array($controller)) continue;
        $name = trim((string)($controller['name'] ?? ''));
        $function = trim((string)($controller['function'] ?? ''));
        $signature = trim((string)($controller['signature_text'] ?? ''));
        if ($name === '' && $function === '' && $signature === '') continue;
        if ($name === '') throw new InvalidArgumentException('Indiquez le nom de chaque membre de la commission.');
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        $functionLength = function_exists('mb_strlen') ? mb_strlen($function, 'UTF-8') : strlen($function);
        $signatureLength = function_exists('mb_strlen') ? mb_strlen($signature, 'UTF-8') : strlen($signature);
        if ($nameLength > 190 || $functionLength > 190 || $signatureLength > 255) throw new InvalidArgumentException('Un nom ou une fonction dépasse 190 caractères, ou une signature dépasse 255 caractères.');
        $normalizedControllers[] = [$name, $function, $signature];
    }
    if (!$normalizedControllers) throw new InvalidArgumentException('Ajoutez au moins un contrôleur à la commission.');
    $metadata = $report['metadata'] ?? [];
    if (!is_array($metadata)) throw new InvalidArgumentException('Informations du contrôle invalides.');
    $safeMetadata = [];
    foreach ($template['fields'] as $key => $_field) {
        if (in_array($key, ['inspection_date', 'commission_label', 'unit_name', 'authorization_number', 'society_name', 'owner_name'], true)) continue;
        $safeMetadata[$key] = trim((string)($metadata[$key] ?? ''));
    }
    foreach ($template['questions'] as $key => $_label) $safeMetadata[$key] = trim((string)($metadata[$key] ?? ''));
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    else $pdo->exec('SAVEPOINT control_create_import');
    try {
        $insert = $pdo->prepare('INSERT INTO control_reports (region_id, province_id, farm_id, inspection_type, inspection_date, commission_label, unit_name, authorization_number, owner_name, metadata_json, conclusion, created_by) VALUES (:region_id, :province_id, :farm_id, :inspection_type, :inspection_date, :commission_label, :unit_name, :authorization_number, :owner_name, :metadata_json, :conclusion, :created_by)');
        $insert->execute([
            ':region_id' => (int)$report['region_id'], ':province_id' => (int)$report['province_id'],
            ':farm_id' => isset($report['farm_id']) && (int)$report['farm_id'] > 0 ? (int)$report['farm_id'] : null, ':inspection_type' => $type,
            ':inspection_date' => (string)$report['inspection_date'], ':commission_label' => trim((string)($report['commission_label'] ?? '')),
            ':unit_name' => trim((string)$report['unit_name']), ':authorization_number' => trim((string)($report['authorization_number'] ?? '')),
            ':owner_name' => trim((string)($report['owner_name'] ?? '')),
            ':metadata_json' => json_encode($safeMetadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':conclusion' => trim((string)($report['conclusion'] ?? '')), ':created_by' => (int)$report['created_by'],
        ]);
        $id = (int)$pdo->lastInsertId();
        $answerInsert = $pdo->prepare('INSERT INTO control_report_answers (report_id, item_key, answer, observation) VALUES (:report_id, :item_key, :answer, :observation)');
        foreach ($normalizedAnswers as $key => [$status, $observation]) {
            if ($status === null && $observation === '') continue;
            $answerInsert->execute([':report_id' => $id, ':item_key' => $key, ':answer' => $status, ':observation' => $observation]);
        }
        $controllerInsert = $pdo->prepare('INSERT INTO control_report_controllers (report_id, sort_order, full_name, function_title, signature_text) VALUES (:report_id, :sort_order, :full_name, :function_title, :signature_text)');
        foreach ($normalizedControllers as $index => [$name, $function, $signature]) {
            $controllerInsert->execute([':report_id' => $id, ':sort_order' => $index + 1, ':full_name' => $name, ':function_title' => $function, ':signature_text' => $signature]);
        }
        if ($photos) persist_control_vehicle_photos($pdo, $id, $photos);
        if ($unitPhotos) persist_control_unit_photos($pdo, $id, $unitPhotos);
        if ($ownsTransaction) $pdo->commit();
        else $pdo->exec('RELEASE SAVEPOINT control_create_import');
        return $id;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        elseif (!$ownsTransaction && $pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT control_create_import');
            $pdo->exec('RELEASE SAVEPOINT control_create_import');
        }
        throw $exception;
    }
}

function control_update(PDO $pdo, int $id, array $report, array $answers, array $controllers, array $photos = [], array $unitPhotos = [], array $deleteVehicleViews = [], array $deleteUnitSlots = []): void
{
    $templates = control_checklists();
    $type = (string)($report['type'] ?? '');
    if (!isset($templates[$type])) throw new InvalidArgumentException('Type de contrôle invalide.');
    $template = $templates[$type];
    $allowedItems = $template['items'];
    if ($type === 'transport') {
        $product = (string)($report['metadata']['products'] ?? '');
        if (!in_array($product, ['poussin', 'eggs', 'live_poultry'], true)) throw new InvalidArgumentException('Choisissez un produit à transporter.');
        $sectionKey = ['poussin' => 'poussin', 'eggs' => 'eggs', 'live_poultry' => 'live_poultry'][$product];
        $allowedKeys = array_merge(array_keys($template['sections']['common']['items']), array_keys($template['sections'][$sectionKey]['items']));
        $allowedItems = array_intersect_key($allowedItems, array_flip($allowedKeys));
        $parts = explode('-', (string)($report['metadata']['registration'] ?? ''));
        if (count($parts) !== 3 || !valid_moroccan_registration($parts[0], $parts[1], $parts[2])) throw new InvalidArgumentException('Le numéro d’immatriculation doit suivre le format XXXXX-lettre-YY.');
    }
    $normalizedAnswers = [];
    foreach ($allowedItems as $key => $_label) {
        $entry = $answers[$key] ?? [];
        if (!is_array($entry)) $entry = [];
        $status = (string)($entry['status'] ?? '');
        if (!in_array($status, ['', 'oui', 'non'], true)) throw new InvalidArgumentException('Réponse de conformité invalide.');
        $observation = trim((string)($entry['observation'] ?? ''));
        if (function_exists('mb_strlen') && mb_strlen($observation, 'UTF-8') > 4000) throw new InvalidArgumentException('Une observation dépasse 4 000 caractères.');
        $normalizedAnswers[$key] = [$status === '' ? null : $status, $observation];
    }
    $safeMetadata = [];
    foreach ($template['fields'] as $key => $_field) {
        if (in_array($key, ['inspection_date', 'commission_label', 'unit_name', 'authorization_number', 'society_name', 'owner_name'], true)) continue;
        $safeMetadata[$key] = trim((string)($report['metadata'][$key] ?? ''));
    }
    foreach ($template['questions'] as $key => $_label) $safeMetadata[$key] = trim((string)($report['metadata'][$key] ?? ''));
    $normalizedControllers = [];
    foreach ($controllers as $controller) {
        if (!is_array($controller)) continue;
        $name = trim((string)($controller['name'] ?? ''));
        $function = trim((string)($controller['function'] ?? ''));
        $signature = trim((string)($controller['signature_text'] ?? ''));
        if ($name === '' && $function === '' && $signature === '') continue;
        if ($name === '') throw new InvalidArgumentException('Indiquez le nom de chaque membre de la commission.');
        if (strlen($name) > 760 || strlen($function) > 760 || strlen($signature) > 1020) throw new InvalidArgumentException('Un nom, une fonction ou une signature dépasse la longueur autorisée.');
        $normalizedControllers[] = [$name, $function, $signature];
    }
    if (!$normalizedControllers) throw new InvalidArgumentException('Ajoutez au moins un contrôleur à la commission.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE control_reports SET farm_id=:farm_id, inspection_date=:inspection_date, commission_label=:commission_label, unit_name=:unit_name, authorization_number=:authorization_number, owner_name=:owner_name, metadata_json=:metadata_json, conclusion=:conclusion WHERE id=:id');
        $stmt->execute([
            ':farm_id' => isset($report['farm_id']) && (int)$report['farm_id'] > 0 ? (int)$report['farm_id'] : null,
            ':inspection_date' => (string)$report['inspection_date'], ':commission_label' => trim((string)($report['commission_label'] ?? '')),
            ':unit_name' => trim((string)($report['unit_name'] ?? '')), ':authorization_number' => trim((string)($report['authorization_number'] ?? '')),
            ':owner_name' => trim((string)($report['owner_name'] ?? '')),
            ':metadata_json' => json_encode($safeMetadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':conclusion' => trim((string)($report['conclusion'] ?? '')), ':id' => $id,
        ]);
        $pdo->prepare('DELETE FROM control_report_answers WHERE report_id=:id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM control_report_controllers WHERE report_id=:id')->execute([':id' => $id]);
        $answerInsert = $pdo->prepare('INSERT INTO control_report_answers (report_id,item_key,answer,observation) VALUES (:id,:key,:answer,:observation)');
        foreach ($normalizedAnswers as $key => [$status,$observation]) if ($status !== null || $observation !== '') $answerInsert->execute([':id'=>$id,':key'=>$key,':answer'=>$status,':observation'=>$observation]);
        $controllerInsert = $pdo->prepare('INSERT INTO control_report_controllers (report_id,sort_order,full_name,function_title,signature_text) VALUES (:id,:sort,:name,:function,:signature)');
        foreach ($normalizedControllers as $index => [$name,$function,$signature]) $controllerInsert->execute([':id'=>$id,':sort'=>$index+1,':name'=>$name,':function'=>$function,':signature'=>$signature]);
        if ($photos || $deleteVehicleViews) persist_control_vehicle_photos($pdo,$id,$photos,$deleteVehicleViews);
        if ($unitPhotos || $deleteUnitSlots) persist_control_unit_photos($pdo,$id,$unitPhotos,$deleteUnitSlots);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
