-- Tamaños precargados (is_system = 1) — A4 es, por lejos, el que ya se usa en
-- casi todos los diseños existentes (21x29.7cm, verificado contra
-- product_pdf_designs real); el resto son tamaños de hoja estándar que se
-- agregan como punto de partida, no inventados para un diseño puntual.
INSERT INTO `pdf_sheet_sizes` (`name`, `width_cm`, `height_cm`, `is_system`, `status_id`, `created_at`, `updated_at`) VALUES
('A4', 21.00, 29.70, 1, 1, NOW(), NOW()),
('A3', 29.70, 42.00, 1, 1, NOW(), NOW()),
('A5', 14.80, 21.00, 1, 1, NOW(), NOW()),
('Oficio', 21.60, 33.00, 1, 1, NOW(), NOW()),
('Carta', 21.60, 27.90, 1, 1, NOW(), NOW());
