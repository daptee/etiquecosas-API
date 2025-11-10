<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\DownloadImageJob;

class DownloadImagesFromCsv extends Command
{
    protected $signature = 'images:download {file}';
    protected $description = 'Descargar imágenes de un CSV';

    public function handle()
    {
        $filePath = $this->argument('file');

        // Convertir rutas relativas a absolutas
        if (str_starts_with($filePath, 'public/')) {
            $filePath = public_path(str_replace('public/', '', $filePath));
        } elseif (str_starts_with($filePath, 'storage/')) {
            $filePath = storage_path(str_replace('storage/', '', $filePath));
        } else {
            // Si es relativa, asumimos desde la raíz del proyecto
            $filePath = base_path($filePath);
        }

        if (!file_exists($filePath)) {
            $this->error("Archivo no encontrado: {$filePath}");
            return;
        }

        $this->info("Procesando CSV: {$filePath}");

        // Leer CSV
        $rows = array_map('str_getcsv', file($filePath));
        $header = array_shift($rows); // quitar encabezado

        foreach ($rows as $row) {
            $data = array_combine($header, $row);

            // Validar que tenga ID y URL de imágenes
            if (!isset($data['ID'], $data['Imágenes'])) {
                continue;
            }

            $productId = $data['ID'];
            $images = array_map('trim', explode(',', $data['Imágenes']));

            foreach ($images as $index => $url) {
                // Despachar job para cada imagen
                DownloadImageJob::dispatch($url, $productId, $index);
            }
        }

        $this->info("✅ Todos los jobs fueron despachados a la cola.");
    }
}
