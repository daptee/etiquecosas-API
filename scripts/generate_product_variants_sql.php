<?php

use League\Csv\Reader;

// Ruta del CSV limpio
$csvPath = __DIR__ . '/storage/app/products_clean.csv';
$sqlPath = __DIR__ . '/storage/app/products_variants.sql';

// Conectar DB para mapear nombres a IDs reales
$pdo = new PDO('mysql:host=127.0.0.1;dbname=etiquecosas', 'root', 'nahuelpass');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Leer CSV
$reader = Reader::createFromPath($csvPath, 'r');
$reader->setHeaderOffset(0);
$records = iterator_to_array($reader->getRecords());

// Generar SQL
$sqlLines = [];

foreach ($records as $record) {
    $nombre = trim($record['Nombre']);
    $variantValues = [];

    // Reunir los atributos si existen
    for ($i = 1; $i <= 3; $i++) {
        $attr = trim($record["Nombre del atributo $i"] ?? '');
        $val  = trim($record["Valor(es) del atributo $i"] ?? '');
        if ($attr && $val) {
            $variantValues[] = "{$attr}: {$val}";
        }
    }

    $variantStr = implode(", ", $variantValues);
    $img = trim($record['Imágenes'] ?? '');

    // Obtener el product_id real desde la DB
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name = :name LIMIT 1");
    $stmt->execute(['name' => $nombre]);
    $productId = $stmt->fetchColumn();

    if (!$productId) {
        echo "⚠️ Producto no encontrado: {$nombre}\n";
        continue;
    }

    $variantEscaped = addslashes($variantStr);
    $imgEscaped = addslashes($img);

    $sqlLines[] = "INSERT INTO product_variants (product_id, variant, img, created_at, updated_at) VALUES ({$productId}, '{$variantEscaped}', '{$imgEscaped}', NOW(), NOW());";
}

// Guardar archivo SQL
file_put_contents($sqlPath, implode("\n", $sqlLines));
echo "SQL generado en: {$sqlPath}\n";
