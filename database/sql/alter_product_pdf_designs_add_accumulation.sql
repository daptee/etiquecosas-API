-- Generaliza lo que hoy hace BandaService (un PDF por día que se va
-- completando a medida que entran pedidos) para que sea un modo más del
-- editor de diseños, sin IDs de producto ni vista Blade hardcodeados.
--
-- fill_mode: 'single' (comportamiento actual, un PDF por pedido) o
--   'accumulating' (la página 1 de `data` define UNA celda — su
--   `sheet.width_cm/height_cm` es el tamaño físico de esa celda — y
--   AccumulatingPdfDesignService la repite para cada pedido del día,
--   agrupando por `accumulation_group_attribute` si está seteado).
-- accumulation_group_attribute: nombre (o parte del nombre) del atributo de
--   la variante a usar para agrupar en páginas separadas (ej. "Color banda
--   1"); NULL = todos los pedidos del día van en una sola lista continua.
-- accumulation_cols / accumulation_rows: celdas por hoja impresa; NULL =
--   se calculan solos según el tamaño de la celda.
-- accumulation_gap_cm: separación entre celdas.
ALTER TABLE `product_pdf_designs`
  ADD COLUMN `fill_mode` VARCHAR(20) NOT NULL DEFAULT 'single' AFTER `is_published`,
  ADD COLUMN `accumulation_group_attribute` VARCHAR(191) NULL AFTER `fill_mode`,
  ADD COLUMN `accumulation_cols` SMALLINT UNSIGNED NULL AFTER `accumulation_group_attribute`,
  ADD COLUMN `accumulation_rows` SMALLINT UNSIGNED NULL AFTER `accumulation_cols`,
  ADD COLUMN `accumulation_gap_cm` DECIMAL(4,2) NOT NULL DEFAULT 0.30 AFTER `accumulation_rows`;
