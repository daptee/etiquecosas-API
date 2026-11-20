-- Líneas de corte del pliego (ver PLIEGOS.md). Mismo formato que
-- product_pdf_designs.data.pages[].cut_lines, más dos opciones propias:
--   { "enabled": true, "width_cm": 0.03,   -- + slot_sources[], designs[], shapes[] (ver PLIEGOS.md)
--     "color": { "mode": "hex", "value": "#FF00FF" },   -- o { "mode": "cmyk", "value": "0,1,0,0" }
--     "labels": true,    -- contorno de cada etiqueta de cada hoja (sale del diseño de la venta)
--     "slots": false }   -- contorno de cada lugar del pliego
-- NULL = deshabilitadas.
ALTER TABLE `pliegos`
  ADD COLUMN `cut_lines` JSON NULL AFTER `gap_mm`;
