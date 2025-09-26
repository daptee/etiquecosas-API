CREATE TABLE tematicas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    colors JSON NOT NULL;
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