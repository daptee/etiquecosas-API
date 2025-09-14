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
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("Archivo no encontrado: $file");
            return;
        }

        $rows = array_map('str_getcsv', file($file));
        $header = array_shift($rows); // quitar encabezado

        foreach ($rows as $row) {
            $data = array_combine($header, $row);
            $productId = $data['ID'];
            $images = array_map('trim', explode(',', $data['Imágenes']));

            foreach ($images as $index => $url) {
                DownloadImageJob::dispatch($url, $productId, $index);
            }
        }

        $this->info("Todos los jobs fueron despachados a la cola.");
    }
}
