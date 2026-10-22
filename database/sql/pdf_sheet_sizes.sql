-- Catálogo de tamaños de hoja ("A4", "Oficio", etc.) para elegir al crear o
-- editar una página en el editor de PDFs — solo completa `sheet.width_cm` /
-- `sheet.height_cm` de esa página, no hay ninguna referencia (FK) desde
-- product_pdf_designs hacia acá: es un catálogo de sugerencia, igual que
-- label_shapes, pero para el tamaño de la HOJA entera (no de la etiqueta).
-- Correr DESPUÉS de pdf_sheet_size_types.sql (+ su seed).
CREATE TABLE `pdf_sheet_sizes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255) NOT NULL,
  `width_cm`    DECIMAL(6,2) NOT NULL,
  `height_cm`   DECIMAL(6,2) NOT NULL,
  -- Página de PDF normal (A4, Oficio, etc.) vs pliego (hoja grande de
  -- imprenta, de la que después se recortan varias etiquetas/páginas) — ver
  -- pdf_sheet_size_types (tabla en vez de ENUM para poder sumar tipos nuevos
  -- sin tocar el esquema).
  `type_id`     BIGINT UNSIGNED NULL,
  `is_system`   TINYINT(1) NOT NULL DEFAULT 0,
  `status_id`   BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pdf_sheet_sizes_name_unique` (`name`),
  CONSTRAINT `pdf_sheet_sizes_status_id_foreign`
    FOREIGN KEY (`status_id`) REFERENCES `general_statuses` (`id`),
  CONSTRAINT `pdf_sheet_sizes_type_id_foreign`
    FOREIGN KEY (`type_id`) REFERENCES `pdf_sheet_size_types` (`id`)
);
