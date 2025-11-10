<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateProductVariantsSql extends Command
{
    protected $signature = 'generate:variants-sql {file : Path to the cleaned CSV file}';
    protected $description = 'Genera un archivo SQL con INSERTS para product_variants desde el CSV de productos';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("Archivo no encontrado: $file");
            return Command::FAILURE;
        }

        $outputFile = storage_path('app/product_variants_inserts.sql');
        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->error("No se pudo abrir el archivo: $file");
            return Command::FAILURE;
        }

        $header = fgetcsv($handle, 0, ",");
        $header = array_map(fn($h) => trim(mb_convert_encoding($h, 'UTF-8', 'UTF-8')), $header);

        $inserts = [];
        $omitted = 0;
        $totalAttributesAdded = 0; // 👈 contador de atributos agregados

        while (($row = fgetcsv($handle, 0, ",")) !== false) {
            $data = array_combine($header, $row);

            if (empty($data['Nombre'])) {
                $omitted++;
                continue;
            }

            $nombreProductoCompleto = trim($data['Nombre']);

            if (str_contains($nombreProductoCompleto, '-')) {
                $parts = explode('-', $nombreProductoCompleto);
                $nombreProducto = trim(implode('-', array_slice($parts, 0, -1)));
            } else {
                $nombreProducto = $nombreProductoCompleto;
            }

            $product = DB::table('products')
                ->where('name', $nombreProducto)
                ->first();

            if (!$product) {
                $this->warn("Producto no encontrado en DB: $nombreProducto");
                $omitted++;
                continue;
            }

            $productId = $product->id;

            $randomNumber = rand(10, 99);

            $words = preg_split('/\s+/', $nombreProducto);
            $initials = '';
            foreach ($words as $word) {
                $letter = preg_replace('/[^A-Za-z]/', '', substr($word, 0, 1));
                if ($letter) {
                    $initials .= strtoupper($letter);
                }
            }

            if (empty($initials)) {
                $initials = 'X';
            }

            $sku = $initials . '-' . $data['ID'];

            $variant = [
                'sku' => $sku,
                'name' => $data['Nombre'],
                'price' => $data['Precio normal'] ?: "0",
                'stock_status' => ($data['¿En stock?'] === '1') ? "1" : "3",
                'stock_quantity' => $data['Stock'] ?: "0",
                'wholesale_price' => $data['Precio de oferta'] ?: "0",
                'wholesale_min_amount' => "0",
                'attributesvalues' => [],
            ];

            for ($i = 1; $i <= 3; $i++) {
                $attrNameKey = "Nombre del atributo $i";
                $attrValueKey = "Valor(es) del atributo $i";

                if (!empty($data[$attrNameKey]) && !empty($data[$attrValueKey])) {
                    $attrName = trim($data[$attrNameKey]);
                    $attrValuesStr = trim($data[$attrValueKey]);
                    $attrValues = array_map('trim', explode('-', $attrValuesStr));

                    $attribute = DB::table('attributes')
                        ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($attrName) . '%'])
                        ->first();

                    if ($attribute) {
                        foreach ($attrValues as $attrValue) {
                            if (empty($attrValue)) continue;

                            $value = DB::table('attribute_values')
                                ->where('attribute_id', $attribute->id)
                                ->whereRaw('LOWER(value) LIKE ?', ['%' . strtolower($attrValue) . '%'])
                                ->first();

                            if ($value) {
                                $variant['attributesvalues'][] = ['id' => $value->id];
                                $totalAttributesAdded++; // 👈 contamos el atributo agregado
                            }
                        }
                    }
                }
            }

            $variantJson = addslashes(json_encode($variant, JSON_UNESCAPED_UNICODE));
            $img = !empty($data['Imágenes']) ? "'images/product_variants/" . basename($data['Imágenes']) . "'" : "NULL";

            $inserts[] = "INSERT INTO product_variants (product_id, variant, img, created_at, updated_at) 
VALUES ($productId, '$variantJson', $img, NOW(), NOW());";
        }

        fclose($handle);

        file_put_contents($outputFile, implode("\n", $inserts));

        $this->info("🎉 Proceso completado. Generados " . count($inserts) . " INSERTS.");
        $this->info("🧩 Total de atributos agregados: $totalAttributesAdded");
        $this->info("⚠️ $omitted filas fueron omitidas.");
        $this->info("📂 Archivo creado en: $outputFile");

        return Command::SUCCESS;
    }
}
