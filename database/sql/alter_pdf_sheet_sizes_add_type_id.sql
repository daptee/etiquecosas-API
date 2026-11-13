-- Para un ambiente donde `pdf_sheet_sizes` YA EXISTÍA sin columna `type` ni
-- `type_id` (creada con la versión anterior a agregar el tipo) — agrega
-- `type_id` directo, sin migrar ningún ENUM (nunca existió acá).
-- Correr DESPUÉS de pdf_sheet_size_types.sql + su seed.
-- (Si en cambio tu tabla tiene una columna `type` ENUM('pagina','pliego') —
-- caso puntual de haber corrido una versión intermedia — usá
-- alter_pdf_sheet_sizes_type_to_fk.sql en vez de este.)
ALTER TABLE `pdf_sheet_sizes` ADD COLUMN `type_id` BIGINT UNSIGNED NULL AFTER `height_cm`;

-- Los tamaños que ya existían son todos páginas normales (A4, Oficio, etc.).
UPDATE `pdf_sheet_sizes`
  SET `type_id` = (SELECT id FROM `pdf_sheet_size_types` WHERE name = 'Página' LIMIT 1)
  WHERE `type_id` IS NULL;

ALTER TABLE `pdf_sheet_sizes`
  ADD CONSTRAINT `pdf_sheet_sizes_type_id_foreign`
    FOREIGN KEY (`type_id`) REFERENCES `pdf_sheet_size_types` (`id`);
