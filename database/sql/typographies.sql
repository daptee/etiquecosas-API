-- 1. Tabla principal de tipografías
CREATE TABLE `typographies` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255) NOT NULL,
  `status_id`  BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `typographies_status_id_foreign`
    FOREIGN KEY (`status_id`) REFERENCES `general_statuses` (`id`)
);

-- 2. Archivos de cada tipografía (TTF, OTF, WOFF, WOFF2, etc.)
CREATE TABLE `typography_files` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `typography_id`  BIGINT UNSIGNED NOT NULL,
  `file_path`      VARCHAR(255) NOT NULL,
  `file_name`      VARCHAR(255) NOT NULL,
  `file_type`      VARCHAR(10)  NOT NULL,
  `created_at`     TIMESTAMP NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `typography_files_typography_id_foreign`
    FOREIGN KEY (`typography_id`) REFERENCES `typographies` (`id`) ON DELETE CASCADE
);

-- 3. Sin columna FK en attribute_values.
-- Las tipografías se enlazan via metadata JSON: { "typography_ids": [1, 2] }
-- igual que colors, icons e images.
