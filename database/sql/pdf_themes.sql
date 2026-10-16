-- Catálogo de temáticas para el editor de PDFs: nombre + paleta de colores
-- (array de hex) + un set de íconos asociados (ver pdf_theme_icons.sql).
-- Independiente de la tabla legacy `tematicas` (la usa el sistema viejo de
-- product_pdf/vistas por temática) — esta es la del editor nuevo, pensada
-- para que un producto o variante pueda asociarse a una y el editor sugiera
-- sus colores/íconos al armar un diseño, sin que nada se aplique solo.
CREATE TABLE `pdf_themes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255) NOT NULL,
  `colors`      JSON NULL,
  `status_id`   BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pdf_themes_name_unique` (`name`),
  CONSTRAINT `pdf_themes_status_id_foreign`
    FOREIGN KEY (`status_id`) REFERENCES `general_statuses` (`id`)
);
