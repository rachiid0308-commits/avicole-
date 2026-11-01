-- Migration idempotente : téléphone et titre professionnel des comptes.
-- À exécuter une fois sur une base existante (phpMyAdmin ou client MySQL).
SET @db_name = DATABASE();

SET @has_mobile_phone = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='users' AND COLUMN_NAME='mobile_phone');
SET @sql_mobile_phone = IF(@has_mobile_phone=0, 'ALTER TABLE users ADD COLUMN mobile_phone VARCHAR(10) NULL AFTER full_name', 'SELECT 1');
PREPARE stmt_mobile_phone FROM @sql_mobile_phone;
EXECUTE stmt_mobile_phone;
DEALLOCATE PREPARE stmt_mobile_phone;

SET @has_professional_title = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='users' AND COLUMN_NAME='professional_title');
SET @sql_professional_title = IF(@has_professional_title=0, 'ALTER TABLE users ADD COLUMN professional_title VARCHAR(120) NULL AFTER mobile_phone', 'SELECT 1');
PREPARE stmt_professional_title FROM @sql_professional_title;
EXECUTE stmt_professional_title;
DEALLOCATE PREPARE stmt_professional_title;
