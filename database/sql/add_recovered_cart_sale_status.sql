-- ============================================================
-- Nuevo estado de venta: "Carrito recuperado"
-- Se asigna automáticamente a la venta ORIGINAL de un carrito
-- abandonado cuando el cliente confirma el pago desde el link de
-- recuperación y el front crea una venta nueva independiente
-- (ver SaleController::store — bloque "sale_id" + status 8).
-- Idempotente: no inserta de nuevo si ya existe.
-- ============================================================
INSERT INTO sale_status (name)
SELECT 'Carrito recuperado'
WHERE NOT EXISTS (SELECT 1 FROM sale_status WHERE name = 'Carrito recuperado');
