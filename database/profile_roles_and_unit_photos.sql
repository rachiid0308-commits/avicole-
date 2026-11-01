-- Migration MySQL 8+ pour les profils hiérarchiques et les photos des visites.
-- À exécuter UNE FOIS sur une base existante après sauvegarde.
ALTER TABLE users DROP CHECK chk_user_assignment;
ALTER TABLE users MODIFY COLUMN role ENUM(
  'central','administrator','central_viewer','regional','division_head','provincial','veterinary_responsible'
) NOT NULL;
ALTER TABLE users ADD CONSTRAINT chk_user_assignment CHECK (
  (role IN ('central','administrator','central_viewer') AND region_id IS NULL AND province_id IS NULL) OR
  (role IN ('regional','division_head') AND region_id IS NOT NULL AND province_id IS NULL) OR
  (role IN ('provincial','veterinary_responsible') AND region_id IS NOT NULL AND province_id IS NOT NULL)
);

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
