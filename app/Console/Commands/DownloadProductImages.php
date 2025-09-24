<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DownloadProductImages extends Command
{
    protected $signature = 'download:product-images {file : Path to the CSV file}';
    protected $description = 'Descarga todas las imágenes de productos desde el CSV y las guarda en public/images/product_variants/';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("Archivo CSV no encontrado: $file");
            return Command::FAILURE;
        }

        $outputDir = public_path('images/product_variants');
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $logFile = storage_path('logs/download_images.log');

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->error("No se pudo abrir el archivo: $file");
            return Command::FAILURE;
        }

        // Leer el header y limpiar caracteres extraños
        $header = fgetcsv($handle, 0, ",");
        $header = array_map(fn($h) => trim(mb_convert_encoding($h, 'UTF-8', 'UTF-8')), $header);

        $downloaded = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, ",")) !== false) {
            $data = array_combine($header, $row);

            $imageUrl = $data['Img'] ?? null;
            if (!$imageUrl) {
                $skipped++;
                continue;
            }

            try {
                $imageContents = @file_get_contents($imageUrl);
                if ($imageContents === false) {
                    $this->warn("No se pudo descargar: $imageUrl");
                    file_put_contents(
                        $logFile,
                        "[" . now() . "] ERROR: No se pudo descargar -> $imageUrl" . PHP_EOL,
                        FILE_APPEND
                    );
                    $skipped++;
                    continue;
                }

                // Usar el mismo nombre que viene en la URL
                $fileName = basename(parse_url($imageUrl, PHP_URL_PATH));
                if (!$fileName) {
                    $fileName = uniqid('product_', true) . '.jpg'; // fallback
                }

                $filePath = $outputDir . '/' . $fileName;
                file_put_contents($filePath, $imageContents);
                $downloaded++;

                $this->info("Imagen guardada: $fileName");
                file_put_contents(
                    $logFile,
                    "[" . now() . "] OK: Imagen guardada -> $filePath" . PHP_EOL,
                    FILE_APPEND
                );
            } catch (\Exception $e) {
                $this->warn("Error al descargar $imageUrl: " . $e->getMessage());
                file_put_contents(
                    $logFile,
                    "[" . now() . "] ERROR: {$e->getMessage()} en -> $imageUrl" . PHP_EOL,
                    FILE_APPEND
                );
                $skipped++;
            }
        }

        fclose($handle);

        $this->info("✅ Descargadas: $downloaded imágenes.");
        $this->info("⚠️ Omitidas: $skipped imágenes.");
        $this->info("📂 Carpeta de destino: $outputDir");
        $this->info("📝 Log generado en: $logFile");

        return Command::SUCCESS;
    }
}
