-- Catálogo de formas/tamaños de etiqueta ("tags") usadas por el editor de PDFs.
-- Incluye tanto formas precargadas (is_system = 1) como las que un admin crea
-- desde el editor (is_system = 0). No reemplaza ni modifica product_pdf.
CREATE TABLE `label_shapes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255) NOT NULL,
  `shape_type`  ENUM('rect','circle','custom') NOT NULL DEFAULT 'rect',
  `width_cm`    DECIMAL(6,2) NOT NULL,
  `height_cm`   DECIMAL(6,2) NOT NULL,
  `is_system`   TINYINT(1) NOT NULL DEFAULT 0,
  `data`        JSON NULL,
  `status_id`   BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `label_shapes_status_id_foreign`
    FOREIGN KEY (`status_id`) REFERENCES `general_statuses` (`id`)
);
