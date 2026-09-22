-- ============================================================
-- Reemplaza el enfoque de sale_status "Carrito recuperado" por una
-- columna boolean en sales. La venta ORIGINAL de un carrito abandonado
-- ya no cambia de estado al recuperarse — sigue "Pendiente de pago" en
-- el admin. La venta NUEVA que la reemplaza queda marcada con
-- is_recovered_cart = 1 (ver SaleController::store).
-- Equivalente en SQL crudo a la migración
-- database/migrations/2026_09_22_000000_add_is_recovered_cart_to_sales_table.php
-- ============================================================

ALTER TABLE sales
    ADD COLUMN is_recovered_cart TINYINT(1) NOT NULL DEFAULT 0 AFTER sale_id;

-- Limpieza del enfoque anterior (solo hace algo si ya se usó el estado
-- "Carrito recuperado" en este ambiente; si nunca se creó, no rompe nada):

-- 1. Marcar como recuperadas las ventas hijas de una venta que haya
--    quedado en "Carrito recuperado".
UPDATE sales
SET is_recovered_cart = 1
WHERE sale_id IN (
    SELECT id FROM (
        SELECT id FROM sales WHERE sale_status_id = (
            SELECT id FROM sale_status WHERE name = 'Carrito recuperado'
        )
    ) AS recovered_parents
);

-- 2. Revertir esas ventas originales a "Pendiente de pago" (8).
UPDATE sales
SET sale_status_id = 8
WHERE sale_status_id = (SELECT id FROM sale_status WHERE name = 'Carrito recuperado');

-- 3. Borrar el estado, ya no se usa.
DELETE FROM sale_status WHERE name = 'Carrito recuperado';
