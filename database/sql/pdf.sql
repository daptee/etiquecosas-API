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

INSERT INTO tematicas (name, colors)
VALUES (
    'Gamer',
    '[
        "0.94,0.59,0.40,0.32",
        "0.06,0.01,0.71,0",
        "0.50,0,0.08,0",
        "0,0,0,0.60"
    ]'
);

INSERT INTO tematicas (name, colors)
VALUES (
    'Sin dibujo blanco y negro',
    '[
        "0,0,0,0",
        "0,0,0,1"
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
    '{"tematicas": [{"id": 154, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Unicornio", "pdf-url": ["principal/UNICORNIO"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    920,
    '{"tematicas": [{"id": 140, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Emojis", "pdf-url": ["principal/EMOJIS"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    929,
    '{"tematicas": [{"id": 139, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Dinosaurios", "pdf-url": ["principal/DINOSAURIOS"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    939,
    '{"tematicas": [{"id": 152, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Teen", "pdf-url": ["principal/TEEN"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    948,
    '{"tematicas": [{"id": 143, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Futbol", "pdf-url": ["principal/FUTBOL"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    956,
    '{"tematicas": [{"id": 146, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Nautica", "pdf-url": ["principal/NAUTICA"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    960,
    '{"tematicas": [{"id": 138, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Bosque", "pdf-url": ["principal/BOSQUE"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    3627,
    '{"tematicas": [{"id": 145, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Llamas", "pdf-url": ["principal/LLAMAS"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    11071,
    '{"tematicas": [{"id": 134, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Granja", "pdf-url": ["principal/GRANJA"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    11128,
    '{"tematicas": [{"id": 153, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Transportes", "pdf-url": ["principal/TRANSPORTES"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    11131,
    '{"tematicas": [{"id": 137, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Basquet", "pdf-url": ["principal/BASQUET"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    11747,
    '{"tematicas": [{"id": 142, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Estrellas pastel", "pdf-url": ["principal/ESTRELLAS PASTEL"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    33879,
    '{"tematicas": [{"id": 327, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Gamer", "pdf-url": ["principal/GAMER"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    31985,
    '{
        "tematicas": [
            {
                "id": 330,
                "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"],
                "pdf-url": ["principal/BLANCO Y NEGRO"],
                "name": "Sin dibujo blanco y negro"
            },
            {
                "id": 148,
                "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"],
                "pdf-url": ["principal/SIN DIBUJO MULTICOLOR"],
                "name": "Sin dibujo multicolor"
            },
            {
                "id": 151,
                "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"],
                "pdf-url": ["principal/SIN DIBUJO SPORTS"],
                "name": "Sin dibujo sports"
            },
            {
                "id": 150,
                "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"],
                "pdf-url": ["principal/SIN DIBUJO RAINBOW"],
                "name": "Sin dibujo rainbow"
            },
            {
                "id": 147,
                "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"],
                "pdf-url": ["principal/SIN DIBUJO BLUE"],
                "name": "Sin dibujo blue"
            },
            {
                "id": 149,
                "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"],
                "pdf-url": ["principal/SIN DIBUJO PASTEL"],
                "name": "Sin dibujo pastel"
            }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    32233,
    '{"tematicas": [{"id": 155, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Skate", "pdf-url": ["principal/SKATE"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    55418,
    '{"tematicas": [{"id": 136, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Aire", "pdf-url": ["principal/AIRE"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    90411,
    '{"tematicas": [{"id": 156, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "name": "Positive", "pdf-url": ["principal/POSITIVE"]}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12332,
    '{"tematicas": [{"id": 143, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/FUTBOL"], "name": "Futbol"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12337,
    '{"tematicas": [{"id": 139, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/DINOSAURIOS"], "name": "Dinosaurios"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12345,
    '{"tematicas": [{"id": 137, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BASQUET"], "name": "Basquet"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12353,
    '{"tematicas": [{"id": 152, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/TEEN"], "name": "Teen"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12361,
    '{"tematicas": [{"id": 145, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/LLAMAS"], "name": "Llamas"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12383,
    '{"tematicas": [{"id": 146, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/NAUTICA"], "name": "Nautica"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12371,
    '{"tematicas": [{"id": 138, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BOSQUE"], "name": "Bosque"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12392,
    '{"tematicas": [{"id": 142, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/ESTRELLAS PASTEL"], "name": "Estrellas pastel"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12647,
    '{"tematicas": [{"id": 134, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/GRANJA"], "name": "Granja"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12400,
    '{"tematicas": [{"id": 153, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/TRANSPORTES"], "name": "Transportes"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12444,
    '{"tematicas": [{"id": 154, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/UNICORNIO"], "name": "Unicornio"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12409,
    '{"tematicas": [{"id": 140, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/EMOJIS"], "name": "Emojis"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    12409,
    '{"tematicas": [{"id": 141, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/ESTRELLAS BLUE"], "name": "Estrellas blue"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    33696,
    '{"tematicas": [{"id": 28, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO"], "name": "Sin dibujo"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    56202,
    '{"tematicas": [{"id": 136, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/AIRE"], "name": "Aire"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    90497,
    '{"tematicas": [{"id": 156, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/POSITIVE"], "name": "Positive"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    59035,
    '{
        "tematicas": [
            {
                "id": 330,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/BLANCO Y NEGRO"],
                "name": "Sin dibujo blanco y negro"
            },
            {
                "id": 148,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/SIN DIBUJO MULTICOLOR"],
                "name": "Sin dibujo multicolor"
            },
            {
                "id": 151,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/SIN DIBUJO SPORTS"],
                "name": "Sin dibujo sports"
            },
            {
                "id": 150,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/SIN DIBUJO RAINBOW"],
                "name": "Sin dibujo rainbow"
            },
            {
                "id": 147,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/SIN DIBUJO BLUE"],
                "name": "Sin dibujo blue"
            },
            {
                "id": 149,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/SIN DIBUJO PASTEL"],
                "name": "Sin dibujo pastel"
            },
            {
                "id": 136,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/AIRE"],
                "name": "Aire"
            },
            {
                "id": 155,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/SKATE"],
                "name": "Skate"
            },
            {
                "id": 142,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/ESTRELLAS PASTEL"],
                "name": "Estrellas pastel"
            },
            {
                "id": 141,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/ESTRELLAS BLUE"],
                "name": "Estrellas blue"
            },
            {
                "id": 145,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/LLAMAS"],
                "name": "Llamas"
            },
            {
                "id": 139,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/DINOSAURIOS"],
                "name": "Dinosaurios"
            },
            {
                "id": 138,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/BOSQUE"],
                "name": "Bosque"
            },
            {
                "id": 144,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/GRANJA"],
                "name": "Granja"
            },
            {
                "id": 154,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/UNICORNIO"],
                "name": "Unicornio"
            },
            {
                "id": 140,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/EMOJIS"],
                "name": "Emojis"
            },
                {
                "id": 152,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/TEEN"],
                "name": "Teen"
            },
            {
                "id": 153,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/TRANSPORTES"],
                "name": "Transportes"
            },
            {
                "id": 143,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/FUTBOL"],
                "name": "Futbol"
            },
            {
                "id": 137,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/BASQUET"],
                "name": "Basquet"
            },
            {
                "id": 146,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/NAUTICA"],
                "name": "Nautica"
            },
            {
                "id": 156,
                "pdf": ["Etiquetas super-maxi"],
                "pdf-url": ["super-maxi/POSITIVE"],
                "name": "Positive"
            }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    1224,
    '{"tematicas": [{"pdf": ["Etiquetas maxi"], "pdf-url": ["maxi/PERSONALIZABLE"], "name": "Personalizacion"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    14120,
    '{
        "tematicas": [
            { "id": 156, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/POSITIVE", "vinilo/POSITIVE"], "name": "Positive" },
            { "id": 151, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/SIN DIBUJO SPORTS", "vinilo/SIN DIBUJO SPORTS"], "name": "Sin dibujo sports" },
            { "id": 148, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/SIN DIBUJO MULTICOLOR", "vinilo/SIN DIBUJO MULTICOLOR"], "name": "Sin dibujo multicolor" },
            { "id": 150, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/SIN DIBUJO RAINBOW", "vinilo/SIN DIBUJO RAINBOW"], "name": "Sin dibujo rainbow" },
            { "id": 147, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/SIN DIBUJO BLUE", "vinilo/SIN DIBUJO BLUE"], "name": "Sin dibujo blue" },
            { "id": 149, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/SIN DIBUJO PASTEL", "vinilo/SIN DIBUJO PASTEL"], "name": "Sin dibujo pastel" },
            { "id": 136, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/AIRE", "vinilo/AIRE"], "name": "Aire" },
            { "id": 155, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/SKATE", "vinilo/SKATE"], "name": "Skate" },
            { "id": 142, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/ESTRELLAS PASTEL", "vinilo/ESTRELLAS PASTEL"], "name": "Estrellas pastel" },
            { "id": 141, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/ESTRELLAS BLUE", "vinilo/ESTRELLAS BLUE"], "name": "Estrellas blue" },
            { "id": 145, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/LLAMAS", "vinilo/LLAMAS"], "name": "Llamas" },
            { "id": 139, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/DINOSAURIOS", "vinilo/DINOSAURIOS"], "name": "Dinosaurios" },
            { "id": 138, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/BOSQUE", "vinilo/BOSQUE"], "name": "Bosque" },
            { "id": 144, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/GRANJA", "vinilo/GRANJA"], "name": "Granja" },
            { "id": 154, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/UNICORNIO", "vinilo/UNICORNIO"], "name": "Unicornio" },
            { "id": 140, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/EMOJIS", "vinilo/EMOJIS"], "name": "Emojis" },
            { "id": 152, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/TEEN", "vinilo/TEEN"], "name": "Teen" },
            { "id": 153, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/TRANSPORTES", "vinilo/TRANSPORTES"], "name": "Transportes" },
            { "id": 143, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/FUTBOL", "vinilo/FUTBOL"], "name": "Futbol" },
            { "id": 137, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/BASQUET", "vinilo/BASQUET"], "name": "Basquet" },
            { "id": 146, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo"], "pdf-url": ["principal/NAUTICA", "vinilo/NAUTICA"], "name": "Nautica" }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    141,
    '{"tematicas": [{"pdf": ["Etiquetas spot and maxi", "Etiquetas planchables"], "pdf-url": ["spot-and-maxi/PERSONALIZABLE", "planchable/PERSONALIZABLE"], "name": "Personalizacion"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    79,
    '{"tematicas": [{"pdf": ["Etiquetas maxi and super maxi and super mini", "Etiquetas planchables"], "pdf-url": ["maxi-and-super-maxi-and-super-mini/PERSONALIZABLE", "planchable/PERSONALIZABLE"], "name": "Personalizacion"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    965,
    '{
        "tematicas": [
            { "id": 323, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Blanco y Negro", "color-range": ["#FFFFFF", "#000000", "#0000FF", "#FFFFFF", "#000000", "#0000FF"] },
            { "id": 319, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Maíz, celeste, azul marino", "color-range": ["#FAAB51", "#65B6BF", "#006B77", "#FAAB51", "#65B6BF", "#006B77"] },
            { "id": 318, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Tierra, rosa, aqua", "color-range": ["#E7C6B5", "#ECA199", "#A4BBB1", "#E7C6B5", "#ECA199", "#A4BBB1"] },
            { "id": 317, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Violeta, fucsia, rosa", "color-range": ["#A25CBF", "#F5426C", "#FEA3BB", "#A25CBF", "#F5426C", "#FEA3BB"] },
            { "id": 316, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "turquesa, salmón, naranja", "color-range": ["#3FD5AE", "#FF8671", "#FFA400", "#3FD5AE", "#FF8671", "#FFA400"] },
            { "id": 315, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Multicolor", "color-range": ["#BA99C4", "#E45C71", "#F09D81", "#D7D769", "#8ACACB", "#E880B1"] },
            { "id": 314, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Rojo, verde oscuro, verde claro", "color-range": ["#ED1C24", "#007549", "#3FAE2A", "#ED1C24", "#007549", "#3FAE2A"] },
            { "id": 313, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Rojo, azul, celeste", "color-range": ["#E13131", "#00628A", "#00B0C1", "#E13131", "#00628A", "#00B0C1"] }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    11194,
    '{
        "tematicas": [
            { "id": 323, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Blanco y Negro", "color-range": ["#FFFFFF", "#000000", "#0000FF", "#FFFFFF", "#000000", "#0000FF"] },
            { "id": 319, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Maíz, celeste, azul marino", "color-range": ["#FAAB51", "#65B6BF", "#006B77", "#FAAB51", "#65B6BF", "#006B77"] },
            { "id": 318, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Tierra, rosa, aqua", "color-range": ["#E7C6B5", "#ECA199", "#A4BBB1", "#E7C6B5", "#ECA199", "#A4BBB1"] },
            { "id": 317, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Violeta, fucsia, rosa", "color-range": ["#A25CBF", "#F5426C", "#FEA3BB", "#A25CBF", "#F5426C", "#FEA3BB"] },
            { "id": 316, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "turquesa, salmón, naranja", "color-range": ["#3FD5AE", "#FF8671", "#FFA400", "#3FD5AE", "#FF8671", "#FFA400"] },
            { "id": 315, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Multicolor", "color-range": ["#BA99C4", "#E45C71", "#F09D81", "#D7D769", "#8ACACB", "#E880B1"] },
            { "id": 314, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Rojo, verde oscuro, verde claro", "color-range": ["#ED1C24", "#007549", "#3FAE2A", "#ED1C24", "#007549", "#3FAE2A"] },
            { "id": 313, "pdf": ["Etiquetas super-mini"], "pdf-url": ["super-mini/COLOR RANGE"], "name": "Rojo, azul, celeste", "color-range": ["#E13131", "#00628A", "#00B0C1", "#E13131", "#00628A", "#00B0C1"] }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    70449,
    '{
        "tematicas": [
            { 
                "id": 307, 
                "name": "Blanco", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER BOLD"], 
                "typography": "Bold",
                "color-range": ["0,0,0,0","0,0,0,0","0,0,0,0","0,0,0,0"],
                "images": ["stars/star-blanco.svg","stars/star-blanco.svg","stars/star-blanco.svg","stars/star-blanco.svg"]
            },
            { 
                "id": 306, 
                "name": "Negro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER BOLD"],
                "typography": "Bold",
                "color-range": ["0,0,0,1","0,0,0,1","0,0,0,1","0,0,0,1"],
                "images": ["stars/star-negro.svg","stars/star-negro.svg","stars/star-negro.svg","stars/star-negro.svg"]
            },
            { 
                "id": 323, 
                "name": "Blanco y Negro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER BOLD"],
                "typography": "Bold",
                "color-range": ["0,0,0,0","0,0,0,1","0,0,0,0","0,0,0,1"],
                "images": ["stars/star-blanco.svg","stars/star-negro.svg","stars/star-blanco.svg","stars/star-negro.svg"]
            },
            { 
                "id": 305, 
                "name": "Verde agua, celeste, rojo, verde oscuro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER BOLD"],
                "typography": "Bold",  
                "color-range": ["0.53,0,0.47,0","0.6,0,0.25,0","0.18,1,0.9,0.08","0.79,0.39,0.9,0.34"],
                "images": ["stars/star-verde-agua.svg","stars/star-celeste.svg","stars/star-rojo.svg","stars/star-verde-oscuro.svg"]
            },
            { 
                "id": 304, 
                "name": "Verde agua, violeta, amarillo, fucsia", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER BOLD"],
                "typography": "Bold",  
                "color-range": ["0.53,0,0.47,0","0.45,0.53,0.23,0.05","0,0.18,0.72,0","0,0.81,0.16,0"],
                "images": ["stars/star-verde-agua.svg","stars/star-violeta.svg","stars/star-amarillo.svg","stars/star-fucsia.svg"]
            },
            { 
                "id": 303, 
                "name": "Celeste, rosa, lila, verde claro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER BOLD"],
                "typography": "Bold",  
                "color-range": ["0.6,0,0.25,0","0.06,0.52,0,0","0.13,0.25,0,0","0.24,0,0.55,0"],
                "images": ["stars/star-celeste.svg","stars/star-rosa.svg","stars/star-lila.svg","stars/star-verde-claro.svg"]
            },
            { 
                "id": 193, 
                "name": "rojo, verde agua, verde lima , azul oscuro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER BOLD"],
                "typography": "Bold",  
                "color-range": ["0.18,1,0.9,0.08","0.53,0,0.47,0","0.2,0,1,0","1,0.95,0.4,0.47"],
                "images": ["stars/star-rojo.svg","stars/star-verde-agua.svg","stars/star-verde-lima.svg","stars/star-azul-oscuro.svg"]
            }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    70467,
    '{
        "tematicas": [
            { 
                "id": 307, 
                "name": "Blanco", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER MAYUSCULA"], 
                "typography": "Mayuscula",
                "color-range": ["0,0,0,0","0,0,0,0","0,0,0,0","0,0,0,0"],
                "images": ["stars/star-blanco.svg","stars/star-blanco.svg","stars/star-blanco.svg","stars/star-blanco.svg"]
            },
            { 
                "id": 306, 
                "name": "Negro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER MAYUSCULA"],
                "typography": "Mayuscula",
                "color-range": ["0,0,0,1","0,0,0,1","0,0,0,1","0,0,0,1"],
                "images": ["stars/star-negro.svg","stars/star-negro.svg","stars/star-negro.svg","stars/star-negro.svg"]
            },
            { 
                "id": 323, 
                "name": "Blanco y Negro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER MAYUSCULA"],
                "typography": "Mayuscula",
                "color-range": ["0,0,0,0","0,0,0,1","0,0,0,0","0,0,0,1"],
                "images": ["stars/star-blanco.svg","stars/star-negro.svg","stars/star-blanco.svg","stars/star-negro.svg"]
            },
            { 
                "id": 305, 
                "name": "Verde agua, celeste, rojo, verde oscuro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER MAYUSCULA"],
                "typography": "Mayuscula",  
                "color-range": ["0.53,0,0.47,0","0.6,0,0.25,0","0.18,1,0.9,0.08","0.79,0.39,0.9,0.34"],
                "images": ["stars/star-verde-agua.svg","stars/star-celeste.svg","stars/star-rojo.svg","stars/star-verde-oscuro.svg"]
            },
            { 
                "id": 304, 
                "name": "Verde agua, violeta, amarillo, fucsia", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER MAYUSCULA"],
                "typography": "Mayuscula",  
                "color-range": ["0.53,0,0.47,0","0.45,0.53,0.23,0.05","0,0.18,0.72,0","0,0.81,0.16,0"],
                "images": ["stars/star-verde-agua.svg","stars/star-violeta.svg","stars/star-amarillo.svg","stars/star-fucsia.svg"]
            },
            { 
                "id": 303, 
                "name": "Celeste, rosa, lila, verde claro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER MAYUSCULA"],
                "typography": "Mayuscula",  
                "color-range": ["0.6,0,0.25,0","0.06,0.52,0,0","0.13,0.25,0,0","0.24,0,0.55,0"],
                "images": ["stars/star-celeste.svg","stars/star-rosa.svg","stars/star-lila.svg","stars/star-verde-claro.svg"]
            },
            { 
                "id": 193, 
                "name": "rojo, verde agua, verde lima, azul oscuro", 
                "pdf": ["Etiquetas transfer"],
                "pdf-url": ["stars/TRANSFER MAYUSCULA"],
                "typography": "Mayuscula",  
                "color-range": ["0.18,1,0.9,0.08","0.53,0,0.47,0","0.2,0,1,0","1,0.95,0.4,0.47"],
                "images": ["stars/star-rojo.svg","stars/star-verde-agua.svg","stars/star-verde-lima.svg","stars/star-azul-oscuro.svg"]
            }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    1233,
    '{"tematicas": [{"pdf": ["Etiquetas spot"], "pdf-url": ["spot/PERSONALIZABLE"], "name": "Personalizacion"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    1247,
    '{
        "tematicas": [
            { 
                "id": 166, 
                "name": "24 etiquetas", 
                "pdf": ["Etiquetas planchables"],
                "pdf-url": ["planchable/PERSONALIZABLE"], 
                "number-labels": 24
            },
            { 
                "id": 167, 
                "name": "48 etiquetas", 
                "pdf": ["Etiquetas planchables"],
                "pdf-url": ["planchable/PERSONALIZABLE"], 
                "number-labels": 48
            }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    1291,
    '{
        "tematicas": [
            { 
                "id": 166, 
                "name": "24 etiquetas", 
                "pdf": ["Etiquetas planchables"],
                "pdf-url": ["planchable/PERSONALIZABLE"], 
                "number-labels": 24
            },
            { 
                "id": 167, 
                "name": "48 etiquetas", 
                "pdf": ["Etiquetas planchables"],
                "pdf-url": ["planchable/PERSONALIZABLE"], 
                "number-labels": 48
            }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    4613,
    '{
        "tematicas": [
            { "id": 136, "name": "Aire", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/AIRE"], "img": "images/product_variants/tematicas-AIRE.jpg" },
            { "id": 156, "name": "Positive", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/POSITIVE"], "img": "images/product_variants/tematicas-positive.jpg" },
            { "id": 148, "name": "Sin dibujo multicolor", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/SIN DIBUJO MULTICOLOR"], "img": "images/product_variants/tematicas-sindibujo-multicolor.jpg" },
            { "id": 151, "name": "Sin dibujo sports", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/SIN DIBUJO SPORTS"], "img": "images/product_variants/tematicas-sindibujo-sports.jpg" },
            { "id": 149, "name": "Sin dibujo pastel", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/SIN DIBUJO PASTEL"], "img": "images/product_variants/tematicas-lisas-pastel.jpg" },
            { "id": 147, "name": "Sin dibujo blue", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/SIN DIBUJO BLUE"], "img": "images/product_variants/tematicas-sindibujo-blue.jpg" },
            { "id": 150, "name": "Sin dibujo rainbow", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/SIN DIBUJO RAINBOW"], "img": "images/product_variants/tematicas-sindibujo-rainbow.jpg" },
            { "id": 142, "name": "Estrellas pastel", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/ESTRELLAS PASTEL"], "img": "images/product_variants/pastel-estrellas.jpg" },
            { "id": 141, "name": "Estrellas blue", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/ESTRELLAS BLUE"], "img": "images/product_variants/PLANCHAS-estrellas.jpg" },
            { "id": 155, "name": "Skate", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/SKATE"], "img": "images/product_variants/PLANCHAS-SKATE.jpg" },
            { "id": 145, "name": "Llamas", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/LLAMAS"], "img": "images/product_variants/PLANCHAS-llamas.jpg" },
            { "id": 139, "name": "Dinosaurios", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/DINOSAURIOS"], "img": "images/product_variants/plancha-dinosaurio.jpg" },
            { "id": 138, "name": "Bosque", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/BOSQUE"], "img": "images/product_variants/PLANCHA.jpg" },
            { "id": 144, "name": "Granja", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/GRANJA"], "img": "images/product_variants/PLANCHAS-granja.jpg" },
            { "id": 154, "name": "Unicornio", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/UNICORNIO"], "img": "images/product_variants/PLANCHAS-unicornio.jpg" },
            { "id": 140, "name": "Emojis", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/EMOJIS"], "img": "images/product_variants/PLANCHA-emoji.jpg" },
            { "id": 152, "name": "Teen", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/TEEN"], "img": "images/product_variants/PLANCHA-teen.jpg" },
            { "id": 153, "name": "Transportes", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/TRANSPORTES"], "img": "images/product_variants/PLANCHAS-transportes.jpg" },
            { "id": 143, "name": "Futbol", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/FUTBOL"], "img": "images/product_variants/plancha-futbol.jpg" },
            { "id": 137, "name": "Basquet", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/BASQUET"], "img": "images/product_variants/PLANCHAS-basquet.jpg" },
            { "id": 146, "name": "Nautica", "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini"], "pdf-url": ["principal/NAUTICA"], "img": "images/product_variants/PLANCHA-MARINO.jpg" }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    852,
    '{"tematicas": [{"pdf": ["Etiquetas maxi"], "pdf-url": ["maxi/PERSONALIZABLE"], "name": "Personalizacion"}]}'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    1239,
    '{
        "tematicas": [
            {"id": 163, "name": "Maxi - Spot", "pdf": ["Etiquetas maxi and spot"], "pdf-url": ["maxi-and-spot/PERSONALIZABLE"], "name": "Personalizacion"},
            {"id": 164, "name": "Maxi - supermini", "pdf": ["Etiquetas maxi and super-mini"], "pdf-url": ["maxi-and-super-mini/PERSONALIZABLE"], "name": "Personalizacion"},
            {"id": 165, "name": "Spot - supermini", "pdf": ["Etiquetas spot and super-mini"], "pdf-url": ["spot-and-super-mini/PERSONALIZABLE"], "name": "Personalizacion"}
        ]
    }'
);


INSERT INTO product_pdf (product_id, data)
VALUES (
    13998,
    '{
        "tematicas": [
            { "id": 330, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/BLANCO Y NEGRO", "vinilo/BLANCO Y NEGRO", "super-mini/BLANCO Y NEGRO"], "name": "Sin dibujo blanco y negro" },
            { "id": 156, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/POSITIVE", "vinilo/POSITIVE", "super-mini/POSITIVE"], "name": "Positive" },
            { "id": 155, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/SKATE", "vinilo/SKATE", "super-mini/SKATE"], "name": "Skate" },
            { "id": 154, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/UNICORNIO", "vinilo/UNICORNIO", "super-mini/UNICORNIO"], "name": "Unicornio" },
            { "id": 153, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/TRANSPORTES", "vinilo/TRANSPORTES", "super-mini/TRANSPORTES"], "name": "Transportes" },
            { "id": 152, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/TEEN", "vinilo/TEEN", "super-mini/TEEN"], "name": "Teen" },
            { "id": 151, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/SIN DIBUJO SPORTS", "vinilo/SIN DIBUJO SPORTS", "super-mini/SIN DIBUJO SPORTS"], "name": "Sin dibujo sports" },
            { "id": 150, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/SIN DIBUJO RAINBOW", "vinilo/SIN DIBUJO RAINBOW", "super-mini/SIN DIBUJO RAINBOW"], "name": "Sin dibujo rainbow" },
            { "id": 149, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/SIN DIBUJO PASTEL", "vinilo/SIN DIBUJO PASTEL", "super-mini/SIN DIBUJO PASTEL"], "name": "Sin dibujo pastel" },
            { "id": 148, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/SIN DIBUJO MULTICOLOR", "vinilo/SIN DIBUJO MULTICOLOR", "super-mini/SIN DIBUJO MULTICOLOR"], "name": "Sin dibujo multicolor" },
            { "id": 147, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/SIN DIBUJO BLUE", "vinilo/SIN DIBUJO BLUE", "super-mini/SIN DIBUJO BLUE"], "name": "Sin dibujo blue" },
            { "id": 146, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/NAUTICA", "vinilo/NAUTICA", "super-mini/NAUTICA"], "name": "Nautica" },
            { "id": 145, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/LLAMAS", "vinilo/LLAMAS", "super-mini/LLAMAS"], "name": "Llamas" },
            { "id": 144, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/GRANJA", "vinilo/GRANJA", "super-mini/GRANJA"], "name": "Granja" },
            { "id": 143, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/FUTBOL", "vinilo/FUTBOL", "super-mini/FUTBOL"], "name": "Futbol" },
            { "id": 142, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/ESTRELLAS PASTEL", "vinilo/ESTRELLAS PASTEL", "super-mini/ESTRELLAS PASTEL"], "name": "Estrellas pastel" },
            { "id": 141, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/ESTRELLAS BLUE", "vinilo/ESTRELLAS BLUE", "super-mini/ESTRELLAS BLUE"], "name": "Estrellas blue" },
            { "id": 140, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/EMOJIS", "vinilo/EMOJIS", "super-mini/EMOJIS"], "name": "Emojis" },
            { "id": 139, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/DINOSAURIOS", "vinilo/DINOSAURIOS", "super-mini/DINOSAURIOS"], "name": "Dinosaurios" },
            { "id": 138, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/BOSQUE", "vinilo/BOSQUE", "super-mini/BOSQUE"], "name": "Bosque" },
            { "id": 137, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/BASQUET", "vinilo/BASQUET", "super-mini/BASQUET"], "name": "Basquet" },
            { "id": 136, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/AIRE", "vinilo/AIRE", "super-mini/AIRE"], "name": "Aire" },
            { "id": 327, "pdf": ["Etiquetas maxi, verticales, super-maxi, super-mini", "Etiquetas vinilo", "Etiquetas super-mini"], "pdf-url": ["principal/GAMER", "vinilo/GAMER", "super-mini/GAMER"], "name": "Gamer" }
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    92903,
    '{
        "tematicas": [
            { "id": 330, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BLANCO Y NEGRO"], "name": "Sin dibujo blanco y negro", "number-labels": 33, "number-columns": 3},
            { "id": 327, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/GAMER"], "name": "Gamer", "number-labels": 33, "number-columns": 3},
            { "id": 156, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/POSITIVE"], "name": "Positive", "number-labels": 33, "number-columns": 3},
            { "id": 155, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SKATE"], "name": "Skate", "number-labels": 33, "number-columns": 3},
            { "id": 154, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/UNICORNIO"], "name": "Unicornio", "number-labels": 33, "number-columns": 3},
            { "id": 153, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/TRANSPORTES"], "name": "Transportes", "number-labels": 33, "number-columns": 3},
            { "id": 152, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/TEEN"], "name": "Teen", "number-labels": 33, "number-columns": 3},
            { "id": 151, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO SPORTS"], "name": "Sin dibujo sports", "number-labels": 33, "number-columns": 3},
            { "id": 150, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO RAINBOW"], "name": "Sin dibujo rainbow", "number-labels": 33, "number-columns": 3},
            { "id": 149, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO PASTEL"], "name": "Sin dibujo pastel", "number-labels": 33, "number-columns": 3},
            { "id": 148, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO MULTICOLOR"], "name": "Sin dibujo multicolor", "number-labels": 33, "number-columns": 3},
            { "id": 147, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO BLUE"], "name": "Sin dibujo blue", "number-labels": 33, "number-columns": 3},
            { "id": 146, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/NAUTICA"], "name": "Nautica", "number-labels": 33, "number-columns": 3},
            { "id": 145, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/LLAMAS"], "name": "Llamas", "number-labels": 33, "number-columns": 3},
            { "id": 144, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/GRANJA"], "name": "Granja", "number-labels": 33, "number-columns": 3},
            { "id": 143, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/FUTBOL"], "name": "Futbol", "number-labels": 33, "number-columns": 3},
            { "id": 142, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/ESTRELLAS PASTEL"], "name": "Estrellas pastel", "number-labels": 33, "number-columns": 3},
            { "id": 141, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/ESTRELLAS BLUE"], "name": "Estrellas blue", "number-labels": 33, "number-columns": 3},
            { "id": 140, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/EMOJIS"], "name": "Emojis", "number-labels": 33, "number-columns": 3},
            { "id": 139, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/DINOSAURIOS"], "name": "Dinosaurios", "number-labels": 33, "number-columns": 3},
            { "id": 138, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BOSQUE"], "name": "Bosque", "number-labels": 33, "number-columns": 3},
            { "id": 137, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BASQUET"], "name": "Basquet", "number-labels": 33, "number-columns": 3},
            { "id": 136, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/AIRE"], "name": "Aire", "number-labels": 33, "number-columns": 3}
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    92901,
    '{
        "tematicas": [
            { "id": 330, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BLANCO Y NEGRO"], "name": "Sin dibujo blanco y negro", "number-labels": 11, "number-columns": 1},
            { "id": 327, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/GAMER"], "name": "Gamer", "number-labels": 11, "number-columns": 1},
            { "id": 156, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/POSITIVE"], "name": "Positive", "number-labels": 11, "number-columns": 1},
            { "id": 155, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SKATE"], "name": "Skate", "number-labels": 11, "number-columns": 1},
            { "id": 154, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/UNICORNIO"], "name": "Unicornio", "number-labels": 11, "number-columns": 1},
            { "id": 153, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/TRANSPORTES"], "name": "Transportes", "number-labels": 11, "number-columns": 1},
            { "id": 152, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/TEEN"], "name": "Teen", "number-labels": 11, "number-columns": 1},
            { "id": 151, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO SPORTS"], "name": "Sin dibujo sports", "number-labels": 11, "number-columns": 1},
            { "id": 150, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO RAINBOW"], "name": "Sin dibujo rainbow", "number-labels": 11, "number-columns": 1},
            { "id": 149, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO PASTEL"], "name": "Sin dibujo pastel", "number-labels": 11, "number-columns": 1},
            { "id": 148, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO MULTICOLOR"], "name": "Sin dibujo multicolor", "number-labels": 11, "number-columns": 1},
            { "id": 147, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO BLUE"], "name": "Sin dibujo blue", "number-labels": 11, "number-columns": 1},
            { "id": 146, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/NAUTICA"], "name": "Nautica", "number-labels": 11, "number-columns": 1},
            { "id": 145, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/LLAMAS"], "name": "Llamas", "number-labels": 11, "number-columns": 1},
            { "id": 144, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/GRANJA"], "name": "Granja", "number-labels": 11, "number-columns": 1},
            { "id": 143, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/FUTBOL"], "name": "Futbol", "number-labels": 11, "number-columns": 1},
            { "id": 142, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/ESTRELLAS PASTEL"], "name": "Estrellas pastel", "number-labels": 11, "number-columns": 1},
            { "id": 141, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/ESTRELLAS BLUE"], "name": "Estrellas blue", "number-labels": 11, "number-columns": 1},
            { "id": 140, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/EMOJIS"], "name": "Emojis", "number-labels": 11, "number-columns": 1},
            { "id": 139, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/DINOSAURIOS"], "name": "Dinosaurios", "number-labels": 11, "number-columns": 1},
            { "id": 138, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BOSQUE"], "name": "Bosque", "number-labels": 11, "number-columns": 1},
            { "id": 137, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BASQUET"], "name": "Basquet", "number-labels": 11, "number-columns": 1},
            { "id": 136, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/AIRE"], "name": "Aire", "number-labels": 11, "number-columns": 1}
        ]
    }'
);

INSERT INTO product_pdf (product_id, data)
VALUES (
    92902,
    '{
        "tematicas": [
            { "id": 330, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BLANCO Y NEGRO"], "name": "Sin dibujo blanco y negro", "number-labels": 22, "number-columns": 2},
            { "id": 327, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/GAMER"], "name": "Gamer", "number-labels": 22, "number-columns": 2},
            { "id": 156, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/POSITIVE"], "name": "Positive", "number-labels": 22, "number-columns": 2},
            { "id": 155, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SKATE"], "name": "Skate", "number-labels": 22, "number-columns": 2},
            { "id": 154, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/UNICORNIO"], "name": "Unicornio", "number-labels": 22, "number-columns": 2},
            { "id": 153, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/TRANSPORTES"], "name": "Transportes", "number-labels": 22, "number-columns": 2},
            { "id": 152, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/TEEN"], "name": "Teen", "number-labels": 22, "number-columns": 2},
            { "id": 151, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO SPORTS"], "name": "Sin dibujo sports", "number-labels": 22, "number-columns": 2},
            { "id": 150, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO RAINBOW"], "name": "Sin dibujo rainbow", "number-labels": 22, "number-columns": 2},
            { "id": 149, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO PASTEL"], "name": "Sin dibujo pastel", "number-labels": 22, "number-columns": 2},
            { "id": 148, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO MULTICOLOR"], "name": "Sin dibujo multicolor", "number-labels": 22, "number-columns": 2},
            { "id": 147, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/SIN DIBUJO BLUE"], "name": "Sin dibujo blue", "number-labels": 22, "number-columns": 2},
            { "id": 146, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/NAUTICA"], "name": "Nautica", "number-labels": 22, "number-columns": 2},
            { "id": 145, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/LLAMAS"], "name": "Llamas", "number-labels": 22, "number-columns": 2},
            { "id": 144, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/GRANJA"], "name": "Granja", "number-labels": 22, "number-columns": 2},
            { "id": 143, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/FUTBOL"], "name": "Futbol", "number-labels": 22, "number-columns": 2},
            { "id": 142, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/ESTRELLAS PASTEL"], "name": "Estrellas pastel", "number-labels": 22, "number-columns": 2},
            { "id": 141, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/ESTRELLAS BLUE"], "name": "Estrellas blue", "number-labels": 22, "number-columns": 2},
            { "id": 140, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/EMOJIS"], "name": "Emojis", "number-labels": 22, "number-columns": 2},
            { "id": 139, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/DINOSAURIOS"], "name": "Dinosaurios", "number-labels": 22, "number-columns": 2},
            { "id": 138, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BOSQUE"], "name": "Bosque", "number-labels": 22, "number-columns": 2},
            { "id": 137, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/BASQUET"], "name": "Basquet", "number-labels": 22, "number-columns": 2},
            { "id": 136, "pdf": ["Etiquetas vinilo"], "pdf-url": ["vinilo/AIRE"], "name": "Aire", "number-labels": 22, "number-columns": 2}
        ]
    }'
);