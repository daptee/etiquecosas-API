<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\Statement;
use Illuminate\Support\Facades\Storage;

class CleanProductNames extends Command
{
    protected $signature = 'migrate:clean-product-names';
    protected $description = 'Limpia los nombres de productos eliminando los atributos anidados al final y genera un nuevo CSV';

    public function handle()
    {
        $this->info("Iniciando limpieza de nombres...");

        $inputPath = storage_path('app/products_variants.csv');
        $outputPath = storage_path('app/products_clean.csv');

        // Leer CSV original
        $reader = Reader::createFromPath($inputPath, 'r');
        $reader->setHeaderOffset(0); // primera fila = encabezados
        $records = (new Statement())->process($reader);

        // Crear writer para nuevo archivo
        $writer = Writer::createFromPath($outputPath, 'w+');
        $writer->insertOne($reader->getHeader()); // copiar encabezados

        $limpiados = 0;

        foreach ($records as $record) {
            $nombreOriginal = $record['Nombre'];
            $atributo1 = trim($record['Valor(es) del atributo 1'] ?? '');
            $atributo2 = trim($record['Valor(es) del atributo 2'] ?? '');
            $atributo3 = trim($record['Valor(es) del atributo 3'] ?? '');

            $nombreLimpio = $nombreOriginal;

            // Solo limpiar si hay UN atributo y el nombre termina con " - atributo"
            if ($atributo1 && !$atributo2 && !$atributo3) {
                $sufijo = " - {$atributo1}";
                if (str_ends_with($nombreOriginal, $sufijo)) {
                    $nombreLimpio = substr($nombreOriginal, 0, -strlen($sufijo));
                    $limpiados++;
                }
            }

            // Sobrescribimos el campo Nombre
            $record['Nombre'] = $nombreLimpio;

            // Guardamos en nuevo CSV
            $writer->insertOne($record);
        }

        $this->info("Limpieza completada ✅ Se corrigieron {$limpiados} nombres.");
        $this->info("Archivo generado en: {$outputPath}");
    }
}
