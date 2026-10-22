-- Reemplaza el ENUM `type` (agregado y dado de baja el mismo día, antes de
-- llegar a usarse en ningún otro ambiente) por una relación a
-- pdf_sheet_size_types — para poder sumar tipos nuevos a futuro sin tocar
-- el esquema. Correr DESPUÉS de pdf_sheet_size_types.sql + su seed.
ALTER TABLE `pdf_sheet_sizes` ADD COLUMN `type_id` BIGINT UNSIGNED NULL AFTER `height_cm`;

UPDATE `pdf_sheet_sizes` ps
  JOIN `pdf_sheet_size_types` t ON t.name = (CASE ps.type WHEN 'pliego' THEN 'Pliego' ELSE 'Página' END)
  SET ps.type_id = t.id;

ALTER TABLE `pdf_sheet_sizes` DROP COLUMN `type`;

ALTER TABLE `pdf_sheet_sizes`
  ADD CONSTRAINT `pdf_sheet_sizes_type_id_foreign`
    FOREIGN KEY (`type_id`) REFERENCES `pdf_sheet_size_types` (`id`);
