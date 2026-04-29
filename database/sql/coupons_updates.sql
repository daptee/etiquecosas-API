-- ============================================================
-- Mejoras al sistema de cupones
-- Ejecutar en orden
-- ============================================================

-- 1. Cupones escalonados (tiered discounts)
ALTER TABLE coupons
    ADD COLUMN tiered_discounts_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER value,
    ADD COLUMN tiered_discounts JSON NULL AFTER tiered_discounts_enabled;

-- Estructura esperada del JSON en tiered_discounts:
-- [
--   { "min_quantity": 2, "type": "Porcentaje", "value": 10 },
--   { "min_quantity": 5, "type": "Porcentaje", "value": 20 },
--   { "min_quantity": 10, "type": "Fijo", "value": 500 }
-- ]
-- Nota: tiered_discounts se persiste aunque tiered_discounts_enabled = 0
-- para no perder la configuración cargada.

-- 2. Cupones relámpago: cambiar date a datetime para poder indicar hora
ALTER TABLE coupons
    MODIFY COLUMN date_from DATETIME NOT NULL,
    MODIFY COLUMN date_to DATETIME NOT NULL;

-- 3. Cupón aplica a toda la web (se aplica automáticamente en el checkout)
ALTER TABLE coupons
    ADD COLUMN applies_to_web TINYINT(1) NOT NULL DEFAULT 0 AFTER tiered_discounts;

-- 4. Cupón aplica sobre precios de oferta
ALTER TABLE coupons
    ADD COLUMN applies_to_sale_price TINYINT(1) NOT NULL DEFAULT 0 AFTER applies_to_web;

-- ============================================================
-- 5. Tabla pivot coupon_sale para múltiples cupones por venta
-- ============================================================
CREATE TABLE coupon_sale (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT NOT NULL,
    coupon_id BIGINT UNSIGNED NOT NULL,
    discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_coupon_sale_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_coupon_sale_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
);

-- Migrar datos existentes: mover coupon_id + discount_amount de sales a la pivot
-- Solo para ventas cuyo coupon_id exista en coupons (incluyendo soft-deleted)
INSERT INTO coupon_sale (sale_id, coupon_id, discount_amount, created_at, updated_at)
SELECT s.id, s.coupon_id, COALESCE(s.discount_amount, 0), s.created_at, s.updated_at
FROM sales s
INNER JOIN coupons c ON c.id = s.coupon_id
WHERE s.coupon_id IS NOT NULL;

-- Nota: La columna coupon_id en sales se mantiene por retrocompatibilidad.
-- Las nuevas ventas usarán exclusivamente la tabla coupon_sale.
