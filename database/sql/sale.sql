CREATE TABLE sale_status (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Precarga
INSERT INTO sale_status (name) VALUES 
('Aprobado'),
('En producción'),
('Pedido listo'),
('Entregado'),
('Cancelado'),
('Pedido casi listo'),
('Pedido retirado'),
('Pendiente de pago');

CREATE TABLE shipping_methods (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Ejemplo de precarga
INSERT INTO shipping_methods (name) VALUES ('Retiro en local'), ('Correo'), ('Envío propio');

CREATE TABLE channels (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Precarga
INSERT INTO channels (name) VALUES ('Web'), ('Local Comercial'), ('MercadoLibre');

CREATE TABLE sales (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NULL, -- referencia a cliente
    channel_id BIGINT NOT NULL, -- canal de venta
    external_id VARCHAR(255) NULL, -- id externo (ej: MercadoLibre)

    -- Datos de envío
    address VARCHAR(255) NULL,
    locality_id BIGINT NULL,
    postal_code VARCHAR(20) NULL,
    client_shipping_id BIGINT NULL, -- si usó dirección guardada del cliente

    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_method_id BIGINT NULL,

    customer_notes TEXT NULL,
    internal_comments TEXT NULL,

    sale_status_id BIGINT NOT NULL, -- estado actual de la venta
    sale_id BIGINT NULL, -- referencia a otra venta (envío agrupado)

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_sales_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_sales_channel FOREIGN KEY (channel_id) REFERENCES channels(id),
    CONSTRAINT fk_sales_shipping_method FOREIGN KEY (shipping_method_id) REFERENCES shipping_methods(id),
    CONSTRAINT fk_sales_status FOREIGN KEY (sale_status_id) REFERENCES sale_status(id),
    CONSTRAINT fk_sales_parent FOREIGN KEY (sale_id) REFERENCES sales(id)
);

CREATE TABLE sales_products (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    customization_data JSON NULL, -- customización hecha por el cliente
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    comment TEXT NULL, -- comentarios del cliente

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_sales_products_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
    CONSTRAINT fk_sales_products_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_product_variants FOREIGN KEY (variant_id) REFERENCES product_variants(id)
);

CREATE TABLE sales_status_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT NOT NULL,
    sale_status_id BIGINT NOT NULL,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_status_history_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
    CONSTRAINT fk_status_history_status FOREIGN KEY (sale_status_id) REFERENCES sale_status(id)
);

ALTER TABLE sales 
ADD COLUMN coupon_id BIGINT UNSIGNED NULL AFTER sale_id,
ADD CONSTRAINT fk_sales_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id);

CREATE TABLE payment_methods (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE, -- nombre del método de pago
    description VARCHAR(255) NULL, -- descripción opcional
    status_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_methods_status FOREIGN KEY (status_id) REFERENCES general_statuses(id)
);

INSERT INTO payment_methods (name, description, status_id) VALUES
('Pago desde la web', 'Pago realizado atravez de la pagina web', 1),
('Efectivo', 'Pago en efectivo en el local', 1),
('Tarjeta de Débito', 'Pago con tarjeta de débito', 1),
('Tarjeta de Crédito', 'Pago con tarjeta de crédito', 1),
('Transferencia Bancaria', 'Transferencia directa a cuenta bancaria', 1),
('QR', 'Pago con QR (ej: MercadoPago, Ualá, etc.)', 1);


ALTER TABLE sales
    ADD COLUMN discount_percent DECIMAL(5,2) NULL AFTER subtotal,
    ADD COLUMN discount_amount DECIMAL(12,2) NULL AFTER discount_percent,
    ADD COLUMN total DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_amount,
    ADD COLUMN payment_method_id BIGINT NULL AFTER total;

-- Llaves foráneas
ALTER TABLE sales
    ADD CONSTRAINT fk_sales_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id);

ALTER TABLE sales
DROP FOREIGN KEY fk_sales_client;

ALTER TABLE sales
MODIFY client_id BIGINT UNSIGNED NULL,
MODIFY address VARCHAR(255) NULL,
MODIFY locality_id BIGINT UNSIGNED NULL,
MODIFY postal_code VARCHAR(20) NULL,
MODIFY shipping_method_id BIGINT NULL;

ALTER TABLE sales
    ADD COLUMN user_id BIGINT UNSIGNED NULL,
    ADD CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users(id);