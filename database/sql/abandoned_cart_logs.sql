-- ============================================================
-- Flujo de carrito abandonado — tabla de registro
-- Equivalente en SQL crudo a la migración de Laravel
-- database/migrations/2026_08_21_000000_create_abandoned_cart_logs_table.php
-- Usar esta alternativa solo si no se corre `php artisan migrate`
-- en el ambiente de destino.
-- ============================================================

CREATE TABLE abandoned_cart_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT NOT NULL, -- sales.id es BIGINT (signed) en este servidor, no UNSIGNED
    client_email VARCHAR(255) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    abandoned_at TIMESTAMP NOT NULL,
    impact_1_sent_at TIMESTAMP NULL DEFAULT NULL,
    impact_2_eligible TINYINT(1) NOT NULL DEFAULT 0,
    impact_2_sent_at TIMESTAMP NULL DEFAULT NULL,
    coupon_id BIGINT UNSIGNED NULL DEFAULT NULL,
    converted_at TIMESTAMP NULL DEFAULT NULL,
    converted_via ENUM('impact_1', 'impact_2') NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY abandoned_cart_logs_sale_id_unique (sale_id),
    KEY abandoned_cart_logs_abandoned_at_index (abandoned_at),
    KEY abandoned_cart_logs_converted_at_index (converted_at),
    CONSTRAINT fk_abandoned_cart_logs_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
    CONSTRAINT fk_abandoned_cart_logs_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id)
);

-- Si se usa este SQL en lugar de la migración de Laravel, hay que registrarla
-- manualmente en la tabla `migrations` para que `php artisan migrate` no
-- intente crearla de nuevo:
-- INSERT INTO migrations (migration, batch)
-- VALUES ('2026_08_21_000000_create_abandoned_cart_logs_table', (SELECT MAX(batch) FROM migrations));

-- ============================================================
-- Cupón fijo del Impacto 2 (ETIQUECARRITO, 15% off)
-- Opcional: si no se inserta a mano, el comando
-- `php artisan carts:process-abandoned` lo crea solo la primera
-- vez que necesita mandar un Impacto 2 (Coupon::firstOrCreate).
-- Se deja acá por si se prefiere tenerlo visible en el panel desde
-- el día 1, en vez de esperar al primer envío real.
-- Es idempotente: no inserta de nuevo si el código ya existe.
-- ============================================================
INSERT INTO coupons (
    name, code, date_from, date_to, min_amount, value,
    tiered_discounts_enabled, applies_to_web, applies_to_sale_price, flash_enabled,
    type, applies_to_all_products, applies_to_shipping,
    max_use_per_user, max_use_per_code, coupon_status_id,
    created_at, updated_at
)
SELECT
    'Recuperación de carrito abandonado', 'ETIQUECARRITO', NOW(), DATE_ADD(NOW(), INTERVAL 5 YEAR), 100000, 15,
    0, 1, 0, 0,
    'Porcentaje', 1, 1,
    1, 0, (SELECT id FROM coupon_statuses WHERE name LIKE '%activ%' LIMIT 1),
    NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM coupons WHERE code = 'ETIQUECARRITO');

-- ============================================================
-- UID público para el link "Ir a mi carrito" de los mails
-- Equivalente en SQL crudo a la migración
-- database/migrations/2026_09_02_000000_add_uid_to_abandoned_cart_logs_table.php
-- ============================================================
ALTER TABLE abandoned_cart_logs
    ADD COLUMN uid CHAR(36) NULL UNIQUE AFTER sale_id;
