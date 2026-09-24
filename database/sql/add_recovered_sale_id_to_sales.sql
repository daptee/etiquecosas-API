-- ============================================================
-- sale_id se reserva exclusivamente para la asociación manual de ventas
-- del admin (PUT /sales/associate/{id}). La recuperación de carrito
-- abandonado usaba ese mismo campo por error — pasa a un campo propio,
-- recovered_sale_id, para no mezclar ambas relaciones.
-- Equivalente en SQL crudo a la migración
-- database/migrations/2026_09_24_000000_add_recovered_sale_id_to_sales_table.php
-- ============================================================

ALTER TABLE sales
    ADD COLUMN recovered_sale_id BIGINT NULL AFTER sale_id,
    ADD CONSTRAINT fk_sales_recovered_sale FOREIGN KEY (recovered_sale_id) REFERENCES sales(id);

-- Migrar los datos que ya se habían guardado en sale_id por el enfoque
-- anterior (solo hace algo si ya se usó; si no, no rompe nada).
UPDATE sales child
INNER JOIN sales parent ON parent.id = child.sale_id
SET child.recovered_sale_id = child.sale_id, child.sale_id = NULL
WHERE parent.is_recovered_cart = 1;
