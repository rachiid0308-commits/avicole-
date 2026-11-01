-- Tables for the aviculture control/checklist module (MySQL/MariaDB).
-- For an already-installed site, import this file once via phpMyAdmin.
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
