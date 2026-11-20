-- Grilla sugerida (columnas × filas) para los tamaños de tipo "Pliego": al
-- elegir el tamaño al crear un pliego, el front completa la distribución con
-- estos valores (editable después). NULL en los tamaños de tipo "Página".
-- Ver PLIEGOS.md. Correr ANTES de pliegos.sql.
ALTER TABLE `pdf_sheet_sizes`
  ADD COLUMN `default_columns` TINYINT UNSIGNED NULL AFTER `type_id`,
  ADD COLUMN `default_rows`    TINYINT UNSIGNED NULL AFTER `default_columns`;

-- Tamaños de pliego precargados (tipo "Pliego", ver
-- seed_pdf_sheet_size_types.sql).
INSERT INTO `pdf_sheet_sizes`
  (`name`, `width_cm`, `height_cm`, `type_id`, `default_columns`, `default_rows`, `is_system`, `status_id`, `created_at`, `updated_at`)
SELECT s.name, s.w, s.h, t.id, s.cols, s.rows_, 1, 1, NOW(), NOW()
FROM (
  SELECT 'Pliego 1 m × 60 cm' AS name, 100.00 AS w, 60.00 AS h, 5 AS cols, 2 AS rows_
  UNION ALL SELECT 'Pliego 1 m × 70 cm', 100.00, 70.00, 5, 2
  UNION ALL SELECT 'Pliego 70 cm × 1 m', 70.00, 100.00, 3, 3
) s
JOIN `pdf_sheet_size_types` t ON t.name = 'Pliego';
