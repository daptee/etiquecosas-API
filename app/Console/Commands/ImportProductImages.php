<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportProductImages extends Command
{
    protected $signature = 'products:import-images {path : Path del CSV}';
    protected $description = 'Importa imágenes desde un CSV y las guarda en storage';

    public function handle()
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("El archivo CSV no existe: {$path}");
            return Command::FAILURE;
        }

        $csv = array_map('str_getcsv', file($path));
        $headers = array_shift($csv); // primera fila como encabezados

        foreach ($csv as $row) {
            // Rellenar columnas vacías para que coincidan con headers
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), null);
            }

            $row = array_combine($headers, $row);

            $id = $row['ID'] ?? null;
            $name = $row['Nombre'] ?? null;
            $imageUrl = $row['Imágenes'] ?? null;

            if (!$imageUrl || empty(trim($imageUrl))) {
                $this->warn("Producto {$id} ({$name}) no tiene imagen, se saltea.");
                continue;
            }

            $fileName = basename(parse_url($imageUrl, PHP_URL_PATH));

            try {
                $imageContents = file_get_contents($imageUrl);
                Storage::disk('public')->put("products-variant/{$fileName}", $imageContents);

                $this->info("Imagen descargada: {$fileName} para producto {$id} ({$name})");
            } catch (\Exception $e) {
                $this->error("Error al descargar {$imageUrl}: " . $e->getMessage());
            }
        }


        return Command::SUCCESS;
    }
}
