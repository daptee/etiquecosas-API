-- Catálogo de "tipos" de tamaño de hoja (Página, Pliego, y los que se
-- agreguen a futuro) — tabla separada en vez de ENUM para poder sumar tipos
-- nuevos sin tocar el esquema (ver pdf_sheet_sizes.type_id).
CREATE TABLE `pdf_sheet_size_types` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255) NOT NULL,
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pdf_sheet_size_types_name_unique` (`name`)
);
