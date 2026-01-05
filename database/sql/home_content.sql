-- Tabla para gestión de contenido de la home
-- Solo permite un registro (validado a nivel de aplicación)
CREATE TABLE home_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content JSON NOT NULL COMMENT 'Estructura JSON con bloques, imágenes, categorías, etc.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para las fechas
CREATE INDEX idx_created_at ON home_content(created_at);
CREATE INDEX idx_updated_at ON home_content(updated_at);

-- Estructura esperada del JSON:
-- {
--   "blocks": [
--     {
--       "id": "block_1",
--       "type": "banner",
--       "order": 1,
--       "title": "Título del bloque",
--       "description": "Descripción del bloque",
--       "images": [
--         {
--           "url": "/storage/home/banner_1.jpg",
--           "order": 1,
--           "is_web": true,
--           "is_mobile": true,
--           "alt": "Banner principal"
--         },
--         {
--           "url": "/storage/home/banner_2.jpg",
--           "order": 2,
--           "is_web": true,
--           "is_mobile": false,
--           "alt": "Banner secundario"
--         }
--       ]
--     },
--     {
--       "id": "block_2",
--       "type": "gallery",
--       "order": 2,
--       "images": [
--         {
--           "url": "/storage/home/gallery_1.jpg",
--           "order": 1,
--           "is_web": true,
--           "is_mobile": true,
--           "alt": "Imagen 1"
--         },
--         {
--           "url": "/storage/home/gallery_2.jpg",
--           "order": 2,
--           "is_web": false,
--           "is_mobile": true,
--           "alt": "Imagen 2"
--         }
--       ]
--     }
--   ],
--   "categories": [
--     {
--       "id": 1,
--       "name": "Categoría 1",
--       "visible": true,
--       "order": 1
--     },
--     {
--       "id": 2,
--       "name": "Categoría 2",
--       "visible": false,
--       "order": 2
--     }
--   ]
-- }
