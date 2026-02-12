-- =============================================
-- MÓDULO DE CADETERÍA
-- Script SQL para ejecutar manualmente en la BD
-- =============================================

-- 1. Nuevo perfil "Cadete" (ID=4)
INSERT INTO profiles (id, name, created_at, updated_at)
VALUES (4, 'Cadete', NOW(), NOW());

-- 2. Tabla pivote usuario-localidades
CREATE TABLE user_localities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    locality_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (locality_id) REFERENCES localities(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_locality (user_id, locality_id)
);

-- 3. Campos de cadetería en ventas
ALTER TABLE sales
ADD COLUMN cadete_id BIGINT UNSIGNED NULL AFTER user_id,
ADD COLUMN receiver_name VARCHAR(255) NULL,
ADD COLUMN receiver_dni VARCHAR(20) NULL,
ADD COLUMN receiver_observations TEXT NULL,
ADD COLUMN delivered_at TIMESTAMP NULL,
ADD FOREIGN KEY (cadete_id) REFERENCES users(id) ON DELETE SET NULL;
