-- Catálogo de tamaños de hoja ("A4", "Oficio", etc.) para elegir al crear o
-- editar una página en el editor de PDFs — solo completa `sheet.width_cm` /
-- `sheet.height_cm` de esa página, no hay ninguna referencia (FK) desde
-- product_pdf_designs hacia acá: es un catálogo de sugerencia, igual que
-- label_shapes, pero para el tamaño de la HOJA entera (no de la etiqueta).
CREATE TABLE `pdf_sheet_sizes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255) NOT NULL,
  `width_cm`    DECIMAL(6,2) NOT NULL,
  `height_cm`   DECIMAL(6,2) NOT NULL,
  `is_system`   TINYINT(1) NOT NULL DEFAULT 0,
  `status_id`   BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pdf_sheet_sizes_name_unique` (`name`),
  CONSTRAINT `pdf_sheet_sizes_status_id_foreign`
    FOREIGN KEY (`status_id`) REFERENCES `general_statuses` (`id`)
);
