<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

class ImportSeoCommand extends Command
{
    protected $signature = 'seo:import-xlsx {file : Ruta al archivo XLSX}';
    protected $description = 'Importa descripciones SEO desde un archivo XLSX y las asigna a productos que no tienen SEO cargado.';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("❌ El archivo no existe: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("📂 Leyendo archivo XLSX: {$filePath}");

        try {
            // ⚠️ Ahora convertimos a colección con encabezados
            $rows = Excel::toCollection(null, $filePath)->first();

            // Detectamos encabezado manualmente (primera fila)
            $headers = $rows->shift()->toArray(); // saca la primera fila
        } catch (\Exception $e) {
            $this->error("Error al leer el archivo XLSX: " . $e->getMessage());
            return Command::FAILURE;
        }

        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $withDataInExcel = 0;

        foreach ($rows as $data) {
            $values = $data->toArray();

            // Emparejamos headers con valores
            $row = array_combine($headers, $values);

            // Normalizamos nombres de columnas
            $id = trim($row['ID'] ?? $row['id'] ?? '');
            $description = trim($row['Meta'] ?? $row['meta'] ?? '');

            if (!$id || !$description) {
                $this->warn("Fila inválida: faltan datos (ID o meta).");
                continue;
            }

            if ($id && $description) {
                $withDataInExcel++;
            }

            $product = Product::find($id);

            if (!$product) {
                $this->warn("Producto con ID {$id} no encontrado.");
                $notFound++;
                continue;
            }

            if (!empty($product->meta_data)) {
                $this->line("Producto ID {$id} ya tiene SEO cargado. Se omite.");
                $skipped++;
                continue;
            }

            $title = "{$product->name} - Etiquecosas";
            $seoJson = json_encode([
                'title' => $title,
                'description' => $description
            ], JSON_UNESCAPED_UNICODE);

            $product->meta_data = $seoJson;
            $product->save();

            $this->info("✅ Producto ID {$id} actualizado con SEO.");
            $updated++;
        }

        $this->newLine();
        $this->info("🧾 Proceso completado:");
        $this->info(" - Productos actualizados: {$updated}");
        $this->info(" - Ya tenían SEO: {$skipped}");
        $this->info(" - No encontrados: {$notFound}");
        $this->info(" - Productos encontrados en el excel: {$withDataInExcel}");

        return Command::SUCCESS;
    }
}
