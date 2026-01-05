-- Tabla para gestión de contenido general de la aplicación
-- Solo permite un registro (validado a nivel de aplicación)
CREATE TABLE general_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content JSON NOT NULL COMMENT 'Estructura JSON con configuración general de la aplicación',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para las fechas
CREATE INDEX idx_created_at ON general_content(created_at);
CREATE INDEX idx_updated_at ON general_content(updated_at);

-- Estructura esperada del JSON (flexible y extensible):
-- {
--   "banner_superior": {
--     "ubicacion": "3 cuotas sin interés",
--     "items": [
--       {
--         "id": 1,
--         "overline": "3 cuotas sin interés",
--         "title": "",
--         "supporting_text": "Supporting line text lorem ipsum dolor sit amet, consectetur.",
--         "order": 1
--       },
--       {
--         "id": 2,
--         "overline": "Envíos gratis",
--         "title": "",
--         "supporting_text": "Supporting line text lorem ipsum dolor sit amet, consectetur.",
--         "order": 2
--       }
--     ]
--   },
--   "sobre_nosotros": {
--     "titulo": "Vení a conocernos a nuestra tienda",
--     "descripcion": "Vas a poder encontrar más de 1.000 productos hechos para vos con dedicación y profesionalismo. Descubrí el mundo del etiquetado y lo fácil y divertido que resulta.",
--     "direccion": "Serrano 394, C1414DEH Cdad. Autónoma de Buenos Aires.",
--     "mapa_url": "http://maps.link"
--   },
--   "newsletter": {
--     "titulo": "Newsletter",
--     "descripcion": "Suscribite en dos simples pasos y enterate primero/a de nuestras novedades.",
--     "activo": true
--   },
--   "contacto": {
--     "email": "info@example.com",
--     "telefono": "+54 11 1234-5678",
--     "whatsapp": "+54 11 1234-5678",
--     "horarios": "Lun a Vie 9-18hs"
--   },
--   "redes_sociales": {
--     "facebook": "https://facebook.com/...",
--     "instagram": "https://instagram.com/...",
--     "twitter": "https://twitter.com/...",
--     "youtube": "https://youtube.com/..."
--   },
--   "configuracion": {
--     "maintenance_mode": false,
--     "mostrar_banner_superior": true,
--     "mostrar_newsletter": true,
--     "mensaje_personalizado": ""
--   }
-- }
