CREATE TABLE shipping_zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    postal_codes JSON NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_shipping_zones_status FOREIGN KEY (status_id) REFERENCES general_statuses(id)
);

-- Tabla shipping_options
CREATE TABLE shipping_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status_id BIGINT UNSIGNED NOT NULL,
    is_shipping_free BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_shipping_options_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE,
    CONSTRAINT fk_shipping_options_status FOREIGN KEY (status_id) REFERENCES general_statuses(id)
);


CREATE TABLE shipping_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data JSON NULL, -- monto mínimo para envío gratuito
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO shipping_config (data)
VALUES ('{
    "free_shipping_min": 100000.00,
    "active": true
}');

ALTER TABLE shipping_options
    ADD COLUMN options_order int NULL DEFAULT 1 AFTER status_id