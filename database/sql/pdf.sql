CREATE TABLE tematicas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    colors JSON NOT NULL
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Aire',
    '[
        "0.01,0.25,0.23,0",
        "0,0,0,0.20",
        "0.09,0.50,0.41,0",
        "0.87,0.76,0.48,0.10",
        "0.36,0,0.34,0",
        "0.05,0.22,0.42,0",
        "0.07,0.29,0.27,0",
        "0.24,0.10,0.13,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Basquet',
    '[
        "0.06,0.33,0.88,0",
        "0.11,0.87,1,0",
        "0.5,0,0.20,0",
        "0.75,0.09,1,0",
        "0%,0.80,0.95,0",
        "1,0.29,0.88,0.19",
        "0.84,0.41,0,0",
        "0.96,0.64,0.32,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Blanco y negro',
    '[
        "0,0,0,0",
        "0,0,0,1"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Bosque',
    '[
        "1,0.27,0.69,0",
        "0,0.86,0.98,0",
        "0.56,0,0.49,0",
        "0.90,0.62,0.82,0.33",
        "0.54,0.50,0.96,0.05",
        "0.43,0.31,1,0",
        "0.71,0,0.42,0",
        "0.32,0.76,0.93,0.01"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Dinosaurios',
    '[
        "0.63,0,0.38,0",
        "0.44,0.71,0.86,0.09",
        "0.59,0,0.98,0",
        "0.83,0.25,0.40,0",
        "0.94,0.34,0.70,0",
        "0.25,0,0.95,0",
        "0.69,0.41,0.91,0.03",
        "0.56,0.40,0.85,0.01",
        "0.32,0.76,0.93,0.01",
        "0.79,0.53,0.39,0",
        "0.37,0.38,0.96,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Emojis',
    '[
        "0.69,0,0.48,0",
        "0.70,0.15,0,0",
        "0.86,0,0.64,0",
        "0.08,0.88,0.80,0",
        "0.39,0,0.67,0",
        "0.53,0.23,0,0",
        "0.09,0.32,0.82,0",
        "0,0.66,0.51,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Estrellas Blue',
    '[
        "0.59,0.10,0.24,0",
        "0.77,0.14,0.29,0",
        "0.96,0.52,0.52,0.03",
        "0.21,0.18,0.18,0",
        "0.45,0,0.43,0",
        "0,0.38,0.77,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Estrellas pastel',
    '[
        "0.08,0.22,0.26,0",
        "0.37,0.17,0.30,0",
        "0.05,0.24,0.16,0",
        "0.25,0.25,0.28,0",
        "0.04,0.44,0.31,0",
        "0.03,0.23,0.33,0",
        "0.21,0.18,0.18,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Futbol',
    '[
        "0.45,0.20,0,0",
        "0.91,0,0.27,0",
        "0.84,0.41,0,0",
        "0.11,0.87,1,0.02",
        "0.49,0,1,0",
        "1,0.29,0.88,0.19",
        "0.96,0.64,0.32,0",
        "0.75,0.09,1,0.01"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Granja',
    '[
        "0.47,0,0.96,0",
        "0.44,0.02,0.03,0",
        "0.20,0.45,0.69,0",
        "0.77,0.14,0.29,0",
        "0,0.38,0.77,0",
        "0,0.94,0.76,0",
        "0.24,0,0.08,0",
        "0.28,0,0.90,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Sin dibujo multicolor',
    '[
        "0.31,0.48,0,0",
        "0,0.83,0.40,0",
        "0,0.50,0.46,0",
        "0,0.70,0,0",
        "0.51,0,0.24,0",
        "0.15,0,0.70,0.10",
        "0.31,0.48,0,0",
        "0.01,0.27,0,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Llamas',
    '[
        "0.04,0.34,0.20,0",
        "0.07,0.35,0.91,0",
        "0.50,0,0.20,0",
        "0.76,0.95,0.50,0.19",
        "0.60,0.70,0,0.10",
        "0.85,0,0.62,0",
        "0.78,0.32,0.24,0",
        "0,0.90,0.39,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Nautica',
    '[
        "0.53,0.23,0,0",
        "0.91,0,0.27,0",
        "0.84,0.41,0,0",
        "0.83,0.23,0.22,0",
        "0.07,0.95,0.91,0",
        "0.96,0.64,0.32,0",
        "0.21,0.18,0.18,0",
        "0,0.83,0.70,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Positive',
    '[
        "0.04,0.30,0.09,0",
        "0.35,0.03,0.16,0",
        "0.09,0.62,0.17,0",
        "0.14,0.19,0.04,0",
        "0.44,0.06,0.36,0",
        "0.05,0.29,0.28,0",
        "0.38,0.41,0.16,0",
        "0.59,0.09,0.31,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Skate',
    '[
        "0.06,0.10,0.93,0",
        "0.02,0.75,0.88,0",
        "0,0,0,0",
        "0.67,0,0.40,0",
        "0.41,0,0.15,0",
        "0,0,0,0.40",
        "0,0,0,1"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Teen',
    '[
        "0.48,0,0,0",
        "0.69,0.86,0,0",
        "0.04,0.18,1,0",
        "0.26,1,0,0",
        "0.86,0.08,0.43,0",
        "0,0.52,0,0",
        "0.03,0.93,0.77,0",
        "0,0.86,0.99,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Transportes',
    '[
        "0.59,0.10,0.24,0",
        "0.77,0.14,0.29,0",
        "0.96,0.52,0.52,0.03",
        "0.21,0.18,0.18,0",
        "0.45,0,0.43,0",
        "0,0.38,0.77,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Unicornio',
    '[
        "0,0.39,0.44,0",
        "0,0.91,0.42,0",
        "0.51,0.48,0,0",
        "0.76,0.95,0.51,0.20",
        "0,0.52,0.13,0",
        "0.32,0.45,0,0",
        "0.38,0.79,0,0",
        "0.54,0.80,0,0",
        "0.09,0.32,0.82,0",
        "0.46,0,0.30,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Sin dibujo sports',
    '[
        "0.45,0.20,0,0",
        "0.91,0,0.27,0",
        "0.84,0.41,0,0",
        "0.11,0.87,1,0.02",
        "0.49,0,1,0",
        "1,0.29,0.88,0.19",
        "0.96,0.64,0.32,0",
        "0.75,0.09,1,0.01"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Sin dibujo Blue',
    '[
        "0.59,0.10,0.24,0",
        "0.77,0.14,0.29,0",
        "0.96,0.52,0.52,0.03",
        "0.21,0.18,0.18,0",
        "0.45,0,0.43,0",
        "0,0.38,0.77,0"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Sin dibujo pastel',
    '[
        "0.08,0.22,0.26,0",
        "0.37,0.17,0.30,0",
        "0.05,0.24,0.16,0",
        "0.25,0.25,0.28,0",
        "0.04,0.44,0.31,0",
        "0.03,0.23,0.33,0",
        "0.21,0.18,0.18,0"
    ]'
);


INSERT INTO tematicas (name, colors)
VALUES (
    'Sin dibujo rainbow',
    '[
        "0,0.39,0.44,0",
        "0,0.91,0.42,0",
        "0.51,0.48,0,0",
        "0.76,0.95,0.51,0.20",
        "0,0.52,0.13,0",
        "0.32,0.45,0,0",
        "0.38,0.79,0,0",
        "0.54,0.80,0,0",
        "0.09,0.32,0.82,0",
        "0.46,0,0.30,0"
    ]'
);

CREATE TABLE product_pdf (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    data JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_pdf FOREIGN KEY (product_id) REFERENCES products(id)
);

// Etiquta tematicas

INSERT INTO product_pdf (product_id, data)
VALUES (
    912,
    '{"tematicas": [{"id": 154, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Unicornio"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    920,
    '{"tematicas": [{"id": 140, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Emojis"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    929,
    '{"tematicas": [{"id": 139, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Dinosaurios"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    939,
    '{"tematicas": [{"id": 152, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Teen"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    948,
    '{"tematicas": [{"id": 143, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Futbol"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    956,
    '{"tematicas": [{"id": 146, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Nautica"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    960,
    '{"tematicas": [{"id": 138, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Bosque"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    3627,
    '{"tematicas": [{"id": 145, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Llamas"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    11071,
    '{"tematicas": [{"id": 134, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Granja"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    11128,
    '{"tematicas": [{"id": 153, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Transportes"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    11131,
    '{"tematicas": [{"id": 137, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Basquet"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    11747,
    '{"tematicas": [{"id": 142, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Estrellas pastel"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    31985,
    '{"tematicas": [{"id": 28, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Sin dibujo"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    32233,
    '{"tematicas": [{"id": 155, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Skate"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    55418,
    '{"tematicas": [{"id": 136, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Aire"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    90411,
    '{"tematicas": [{"id": 156, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Positive"}]}'
);

// Etiquetas vinilo

INSERT INTO product_pdf (product_id, data)
VALUES (
    12332,
    '{"tematicas": [{"id": 143, "pdf": ["Etiquetas vinilo"], "name": "Futbol"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12337,
    '{"tematicas": [{"id": 139, "pdf": ["Etiquetas vinilo"], "name": "Dinosaurios"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12345,
    '{"tematicas": [{"id": 137, "pdf": ["Etiquetas vinilo"], "name": "Basquet"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12353,
    '{"tematicas": [{"id": 152, "pdf": ["Etiquetas vinilo"], "name": "Teen"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12361,
    '{"tematicas": [{"id": 145, "pdf": ["Etiquetas vinilo"], "name": "Llamas"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12383,
    '{"tematicas": [{"id": 146, "pdf": ["Etiquetas vinilo"], "name": "Nautica"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12371,
    '{"tematicas": [{"id": 138, "pdf": ["Etiquetas vinilo"], "name": "Bosque"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12392,
    '{"tematicas": [{"id": 142, "pdf": ["Etiquetas vinilo"], "name": "Estrellas pastel"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12647,
    '{"tematicas": [{"id": 134, "pdf": ["Etiquetas vinilo"], "name": "Granja"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12400,
    '{"tematicas": [{"id": 153, "pdf": ["Etiquetas vinilo"], "name": "Transportes"}]}'
);

VALUES (
    12444,
    '{"tematicas": [{"id": 154, "pdf": ["Etiquetas vinilo"], "name": "Unicornio"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12409,
    '{"tematicas": [{"id": 140, "pdf": ["Etiquetas vinilo"], "name": "Emojis"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12409,
    '{"tematicas": [{"id": 141, "pdf": ["Etiquetas vinilo"], "name": "Estrellas blue"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    33696,
    '{"tematicas": [{"id": 28, "pdf": ["Etiquetas vinilo"], "name": "Sin dibujo"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    56202,
    '{"tematicas": [{"id": 136, "pdf": ["Etiquetas vinilo"], "name": "Aire"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    90497,
    '{"tematicas": [{"id": 156, "pdf": ["Etiquetas vinilo"], "name": "Positive"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    59035,
    '{
        "tematicas": [
            { "id": 148, "pdf": ["Etiquetas super-maxi"], "name": "Sin dibujo multicolor" },
            { "id": 151, "pdf": ["Etiquetas super-maxi"], "name": "Sin dibujo sports" },
            { "id": 150, "pdf": ["Etiquetas super-maxi"], "name": "Sin dibujo rainbow" },
            { "id": 147, "pdf": ["Etiquetas super-maxi"], "name": "Sin dibujo blue" },
            { "id": 149, "pdf": ["Etiquetas super-maxi"], "name": "Sin dibujo pastel" },
            { "id": 136, "pdf": ["Etiquetas super-maxi"], "name": "Aire" },
            { "id": 155, "pdf": ["Etiquetas super-maxi"], "name": "Skate" },
            { "id": 142, "pdf": ["Etiquetas super-maxi"], "name": "Estrellas pastel" },
            { "id": 141, "pdf": ["Etiquetas super-maxi"], "name": "Estrellas blue" },
            { "id": 145, "pdf": ["Etiquetas super-maxi"], "name": "Llamas" },
            { "id": 139, "pdf": ["Etiquetas super-maxi"], "name": "Dinosaurios" },
            { "id": 138, "pdf": ["Etiquetas super-maxi"], "name": "Bosque" },
            { "id": 144, "pdf": ["Etiquetas super-maxi"], "name": "Granja" },
            { "id": 154, "pdf": ["Etiquetas super-maxi"], "name": "Unicornio" },
            { "id": 140, "pdf": ["Etiquetas super-maxi"], "name": "Emojis" },
            { "id": 152, "pdf": ["Etiquetas super-maxi"], "name": "Teen" },
            { "id": 153, "pdf": ["Etiquetas super-maxi"], "name": "Transportes" },
            { "id": 143, "pdf": ["Etiquetas super-maxi"], "name": "Futbol" },
            { "id": 137, "pdf": ["Etiquetas super-maxi"], "name": "Basquet" },
            { "id": 146, "pdf": ["Etiquetas super-maxi"], "name": "Nautica" }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    1224,
    '{"tematicas": [{"pdf": ["Etiquetas maxi"], "name": "Personalizacion"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    14120,
    '{
        "tematicas": [
            { "id": 156, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Positive" },
            { "id": 151, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Sin dibujo sports" },
            { "id": 148, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Sin dibujo multicolor" },
            { "id": 150, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Sin dibujo rainbow" },
            { "id": 147, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Sin dibujo blue" },
            { "id": 149, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Sin dibujo pastel" },
            { "id": 136, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Aire" },
            { "id": 155, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Skate" },
            { "id": 142, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Estrellas pastel" },
            { "id": 141, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Estrellas blue" },
            { "id": 145, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Llamas" },
            { "id": 139, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Dinosaurios" },
            { "id": 138, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Bosque" },
            { "id": 144, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Granja" },
            { "id": 154, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Unicornio" },
            { "id": 140, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Emojis" },
            { "id": 152, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Teen" },
            { "id": 153, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Transportes" },
            { "id": 143, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Futbol" },
            { "id": 137, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Basquet" },
            { "id": 146, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "name": "Nautica" }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    141,
    '{"tematicas": [{"pdf": ["Etiquetas spot and maxi", "Etiquetas planchables"], "name": "Personalizacion"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    79,
    '{"tematicas": [{"pdf": ["Etiquetas maxi and super maxi and super mini", "Etiquetas planchables"], "name": "Personalizacion"}]}'
);

data: '{"tematicas": [{"id": 154, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "name": "unicornio"}]}'