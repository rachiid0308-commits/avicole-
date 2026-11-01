-- MariaDB/MySQL 8+ ; importer après avoir créé la base aviculture.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS regions (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_regions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provinces (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  region_id SMALLINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_province_region_name (region_id, name),
  KEY idx_provinces_region (region_id),
  CONSTRAINT fk_provinces_region FOREIGN KEY (region_id) REFERENCES regions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  professional_title VARCHAR(120) NULL,
  mobile_phone VARCHAR(10) NULL,
  role ENUM('central','administrator','central_viewer','regional','division_head','provincial','veterinary_responsible') NOT NULL,
  region_id SMALLINT UNSIGNED NULL,
  province_id SMALLINT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role), KEY idx_users_region (region_id), KEY idx_users_province (province_id),
  CONSTRAINT fk_users_region FOREIGN KEY (region_id) REFERENCES regions(id),
  CONSTRAINT fk_users_province FOREIGN KEY (province_id) REFERENCES provinces(id),
  CONSTRAINT chk_user_assignment CHECK (
    (role IN ('central','administrator','central_viewer') AND region_id IS NULL AND province_id IS NULL) OR
    (role IN ('regional','division_head') AND region_id IS NOT NULL AND province_id IS NULL) OR
    (role IN ('provincial','veterinary_responsible') AND region_id IS NOT NULL AND province_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legacy_imports (
  sha256 CHAR(64) NOT NULL,
  source_name VARCHAR(255) NOT NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  imported_rows INT UNSIGNED NOT NULL,
  PRIMARY KEY (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS farms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  region_id SMALLINT UNSIGNED NOT NULL,
  province_id SMALLINT UNSIGNED NOT NULL,
  authorization_status ENUM('Accord de principe','Accord refusé','Autorisé','Suspendue','Retrait') NOT NULL,
  agreement_number VARCHAR(100) NULL,
  authorization_number VARCHAR(100) NULL,
  owner_name VARCHAR(190) NULL,
  national_id VARCHAR(50) NULL,
  farm_name VARCHAR(190) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  farm_type ENUM('Chair','Pondeuses','Reproducteurs','Couvoir','Dinde') NOT NULL,
  nearest_farm_name VARCHAR(190) NULL,
  nearest_distance_km DECIMAL(9,3) NULL,
  first_agreement_date DATE NULL,
  second_agreement_date DATE NULL,
  construction_deadline DATE NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_farms_region_province (region_id, province_id),
  KEY idx_farms_status (authorization_status), KEY idx_farms_type (farm_type),
  KEY idx_farms_name (farm_name),
  CONSTRAINT fk_farms_region FOREIGN KEY (region_id) REFERENCES regions(id),
  CONSTRAINT fk_farms_province FOREIGN KEY (province_id) REFERENCES provinces(id),
  CONSTRAINT fk_farms_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_farms_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_farms_latitude CHECK (latitude BETWEEN -90 AND 90),
  CONSTRAINT chk_farms_longitude CHECK (longitude BETWEEN -180 AND 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO regions (id, name) VALUES
(1,'Tanger-Tétouan-Al Hoceïma'),(2,'Oriental'),(3,'Fès-Meknès'),(4,'Rabat-Salé-Kénitra'),
(5,'Béni Mellal-Khénifra'),(6,'Casablanca-Settat'),(7,'Marrakech-Safi'),(8,'Drâa-Tafilalet'),
(9,'Souss-Massa'),(10,'Guelmim-Oued Noun'),(11,'Laâyoune-Sakia El Hamra'),(12,'Dakhla-Oued Ed-Dahab');

INSERT IGNORE INTO provinces (region_id, name) VALUES
(1,'Al Hoceïma'),(1,'Chefchaouen'),(1,'Fahs-Anjra'),(1,'Larache'),(1,'Ouezzane'),(1,'Tanger-Assilah'),(1,'Tétouan'),(1,'M''Diq-Fnideq'),
(2,'Berkane'),(2,'Driouch'),(2,'Figuig'),(2,'Guercif'),(2,'Jerada'),(2,'Nador'),(2,'Oujda-Angad'),(2,'Taourirt'),
(3,'Boulemane'),(3,'El Hajeb'),(3,'Fès'),(3,'Ifrane'),(3,'Meknès'),(3,'Moulay Yacoub'),(3,'Sefrou'),(3,'Taounate'),(3,'Taza'),
(4,'Kénitra'),(4,'Khémisset'),(4,'Rabat'),(4,'Salé'),(4,'Sidi Kacem'),(4,'Sidi Slimane'),(4,'Skhirate-Témara'),
(5,'Azilal'),(5,'Béni Mellal'),(5,'Fquih Ben Salah'),(5,'Khénifra'),(5,'Khouribga'),
(6,'Benslimane'),(6,'Berrechid'),(6,'Casablanca'),(6,'El Jadida'),(6,'Médiouna'),(6,'Mohammédia'),(6,'Nouaceur'),(6,'Settat'),(6,'Sidi Bennour'),
(7,'Al Haouz'),(7,'Chichaoua'),(7,'El Kelâa des Sraghna'),(7,'Essaouira'),(7,'Marrakech'),(7,'Rehamna'),(7,'Safi'),(7,'Youssoufia'),
(8,'Errachidia'),(8,'Midelt'),(8,'Ouarzazate'),(8,'Tinghir'),(8,'Zagora'),
(9,'Agadir-Ida-Ou-Tanane'),(9,'Chtouka-Aït Baha'),(9,'Inezgane-Aït Melloul'),(9,'Taroudant'),(9,'Tata'),(9,'Tiznit'),
(10,'Assa-Zag'),(10,'Guelmim'),(10,'Sidi Ifni'),(10,'Tan-Tan'),
(11,'Boujdour'),(11,'Es-Semara'),(11,'Laâyoune'),(11,'Tarfaya'),
(12,'Aousserd'),(12,'Oued Ed-Dahab');

-- Formulaires de contrôle et rapports
CREATE TABLE IF NOT EXISTS control_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  region_id SMALLINT UNSIGNED NOT NULL,
  province_id SMALLINT UNSIGNED NOT NULL,
  farm_id BIGINT UNSIGNED NULL,
  inspection_type ENUM('chair','reproducteurs','pondeuses','transport') NOT NULL,
  inspection_date DATE NOT NULL,
  commission_label VARCHAR(190) NULL,
  unit_name VARCHAR(190) NOT NULL,
  authorization_number VARCHAR(100) NULL,
  owner_name VARCHAR(190) NULL,
  metadata_json LONGTEXT NOT NULL,
  conclusion TEXT NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_control_reports_geo_date (region_id, province_id, inspection_date),
  KEY idx_control_reports_type (inspection_type),
  KEY idx_control_reports_farm (farm_id),
  CONSTRAINT fk_control_reports_region FOREIGN KEY (region_id) REFERENCES regions(id),
  CONSTRAINT fk_control_reports_province FOREIGN KEY (province_id) REFERENCES provinces(id),
  CONSTRAINT fk_control_reports_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL,
  CONSTRAINT fk_control_reports_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_report_answers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  report_id BIGINT UNSIGNED NOT NULL,
  item_key VARCHAR(80) NOT NULL,
  answer ENUM('oui','non') NULL,
  observation TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_control_report_answer (report_id, item_key),
  CONSTRAINT fk_control_answer_report FOREIGN KEY (report_id) REFERENCES control_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_report_controllers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  report_id BIGINT UNSIGNED NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  full_name VARCHAR(190) NOT NULL,
  function_title VARCHAR(190) NULL,
  signature_text VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_control_controllers_report (report_id, sort_order),
  CONSTRAINT fk_control_controller_report FOREIGN KEY (report_id) REFERENCES control_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_report_photos (
  report_id BIGINT UNSIGNED NOT NULL,
  view_key ENUM('front','rear','right','left') NOT NULL,
  mime_type ENUM('image/jpeg','image/png','image/webp') NOT NULL,
  image_data MEDIUMBLOB NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (report_id, view_key),
  CONSTRAINT fk_control_photo_report FOREIGN KEY (report_id) REFERENCES control_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_report_unit_photos (
  report_id BIGINT UNSIGNED NOT NULL,
  photo_slot TINYINT UNSIGNED NOT NULL,
  mime_type ENUM('image/jpeg','image/png','image/webp') NOT NULL,
  image_data MEDIUMBLOB NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (report_id, photo_slot),
  CONSTRAINT fk_control_unit_photo_report FOREIGN KEY (report_id) REFERENCES control_reports(id) ON DELETE CASCADE,
  CONSTRAINT chk_control_unit_photo_slot CHECK (photo_slot BETWEEN 1 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

