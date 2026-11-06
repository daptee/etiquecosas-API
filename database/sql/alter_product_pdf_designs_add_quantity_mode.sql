-- Cómo se imprime un pedido con cantidad > 1 dentro de un diseño acumulable
-- (ver AccumulatingPdfDesignService):
--   'duplicate' (default): se repite la celda esa cantidad de veces (N
--     etiquetas físicas separadas).
--   'badge': UNA sola celda + un cartelito "xN" al lado (como ya hace hoy
--     CintaCoserService/CintaPlancharService).
ALTER TABLE `product_pdf_designs`
  ADD COLUMN `accumulation_quantity_mode` VARCHAR(20) NOT NULL DEFAULT 'duplicate' AFTER `accumulation_gap_cm`;
