-- Pliegos: planchas grandes de imprenta (ej. 1 m × 60 cm) divididas en una
-- grilla de "lugares", donde cada lugar lleva UNA hoja (página) de un PDF ya
-- generado de una venta (storage/app/pdf/planchas/{fecha}/{venta}-{saleProduct}-...pdf).
-- Ver PLIEGOS.md.
--
-- Correr DESPUÉS de alter_pdf_sheet_sizes_add_default_grid.sql (sheet_size_id
-- apunta a pdf_sheet_sizes, solo informativo: el tamaño real queda copiado en
-- width_cm/height_cm, igual que el editor de PDFs).
CREATE TABLE `pliegos` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(255) NOT NULL,
  `sheet_size_id`  BIGINT UNSIGNED NULL,
  `width_cm`       DECIMAL(6,2) NOT NULL,
  `height_cm`      DECIMAL(6,2) NOT NULL,
  `columns`        TINYINT UNSIGNED NOT NULL,
  `rows`           TINYINT UNSIGNED NOT NULL,
  `margin_mm`      DECIMAL(6,2) NOT NULL DEFAULT 0,
  `gap_mm`         DECIMAL(6,2) NOT NULL DEFAULT 0,
  `status`         ENUM('borrador', 'finalizado') NOT NULL DEFAULT 'borrador',
  `finalized_at`   TIMESTAMP NULL DEFAULT NULL,
  `finalized_by`   BIGINT UNSIGNED NULL,
  `created_by`     BIGINT UNSIGNED NULL,
  `created_at`     TIMESTAMP NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pliegos_status_index` (`status`),
  CONSTRAINT `pliegos_sheet_size_id_foreign`
    FOREIGN KEY (`sheet_size_id`) REFERENCES `pdf_sheet_sizes` (`id`) ON DELETE SET NULL
);

-- Un lugar ocupado del pliego (los vacíos no tienen fila).
-- La hoja se identifica por archivo + página: (pdf_folder, pdf_file, page_number).
-- La restricción única sobre esos 3 campos es la regla "una hoja solo puede
-- estar en UN pliego a la vez". Al eliminar un pliego se borran sus filas
-- (no soft delete) para que las hojas vuelvan a quedar disponibles.
CREATE TABLE `pliego_slots` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pliego_id`        BIGINT UNSIGNED NOT NULL,
  `position`         SMALLINT UNSIGNED NOT NULL, -- 0-based, izq→der y arriba→abajo
  `sale_id`          BIGINT NOT NULL,
  `sale_product_id`  BIGINT NOT NULL,
  `pdf_folder`       VARCHAR(10) NOT NULL,   -- "d-m-Y" (carpeta de planchas)
  `pdf_file`         VARCHAR(500) NOT NULL,  -- nombre del archivo dentro de la carpeta
  `page_number`      SMALLINT UNSIGNED NOT NULL, -- 1-based
  `created_at`       TIMESTAMP NULL DEFAULT NULL,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pliego_slots_pliego_position_unique` (`pliego_id`, `position`),
  UNIQUE KEY `pliego_slots_sheet_unique` (`pdf_folder`, `pdf_file`, `page_number`),
  KEY `pliego_slots_sale_id_index` (`sale_id`),
  CONSTRAINT `pliego_slots_pliego_id_foreign`
    FOREIGN KEY (`pliego_id`) REFERENCES `pliegos` (`id`) ON DELETE CASCADE
);
