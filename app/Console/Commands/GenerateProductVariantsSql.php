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

        // Leer header y limpiar caracteres extraños
        $header = fgetcsv($handle, 0, ",");
        $header = array_map(fn($h) => trim(mb_convert_encoding($h, 'UTF-8', 'UTF-8')), $header);

        $inserts = [];
        $omitted = 0;

        while (($row = fgetcsv($handle, 0, ",")) !== false) {
            $data = array_combine($header, $row);

            if (empty($data['Nombre'])) {
                $omitted++;
                continue;
            }

            $nombreProducto = trim($data['Nombre']);

            // Buscar el ID del producto existente por nombre
            $product = DB::table('products')
                ->where('name', $nombreProducto)
                ->first();

            if (!$product) {
                $this->warn("Producto no encontrado en DB: $nombreProducto");
                $omitted++;
                continue;
            }

            $productId = $product->id;

            // Construir JSON de variante
            $variant = [
                'sku' => $data['SKU'] ?: 'null',
                'price' => $data['Precio normal'] ?: "0",
                'stock_status' => ($data['¿En stock?'] === '1') ? "1" : "3",
                'stock_quantity' => $data['Stock'] ?: "0",
                'wholesale_price' => $data['Oferta'] ?: "0",
                'wholesale_min_amount' => "0",
                'attributesvalues' => [],
            ];

            // Recorrer hasta 3 atributos
            for ($i = 1; $i <= 3; $i++) {
                $attrNameKey = "Nombre del atributo $i";
                $attrValueKey = "Valor(es) del atributo $i";

                if (!empty($data[$attrNameKey]) && !empty($data[$attrValueKey])) {
                    $attrName = trim($data[$attrNameKey]);
                    $attrValuesStr = trim($data[$attrValueKey]);

                    // Separar por guiones si existen múltiples valores
                    $attrValues = array_map('trim', explode('-', $attrValuesStr));

                    // Buscar atributo por coincidencia parcial y sin distinción de mayúsculas
                    $attribute = DB::table('attributes')
                        ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($attrName) . '%'])
                        ->first();

                    if ($attribute) {
                        foreach ($attrValues as $attrValue) {
                            if (empty($attrValue)) continue;

                            // Buscar valor del atributo relacionado también con coincidencia parcial
                            $value = DB::table('attribute_values')
                                ->where('attribute_id', $attribute->id)
                                ->whereRaw('LOWER(value) LIKE ?', ['%' . strtolower($attrValue) . '%'])
                                ->first();

                            if ($value) {
                                $variant['attributesvalues'][] = ['id' => $value->id];
                            }
                        }
                    }
                }
            }

            $variantJson = json_encode($variant, JSON_UNESCAPED_UNICODE);
            $img = $data['Img'] ?: null;

            $inserts[] = "INSERT INTO product_variants (product_id, variant, img, created_at, updated_at) VALUES ($productId, '$variantJson', $img, NOW(), NOW());";
        }

        fclose($handle);

        file_put_contents($outputFile, implode("\n", $inserts));

        $this->info("🎉 Proceso completado. Generados " . count($inserts) . " INSERTS.");
        $this->info("⚠️ $omitted filas fueron omitidas.");
        $this->info("📂 Archivo creado en: $outputFile");

        return Command::SUCCESS;
    }
}
