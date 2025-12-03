ALTER TABLE client_wholesales
    ADD COLUMN postal_code VARCHAR(20) NULL

ALTER TABLE client_addresses
    ADD COLUMN postal_code VARCHAR(20) NULL,
    ADD COLUMN name VARCHAR(150) NULL,
    ADD COLUMN observations TEXT NULL

ALTER TABLE clients
    DROP COLUMN billing_data,
    DROP COLUMN wholesale_data,
    ADD COLUMN business_name VARCHAR(255) NULL;