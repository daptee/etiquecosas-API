<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Statement;
use Carbon\Carbon;

class MigrateProductCosts extends Command
{
    protected $signature = 'migrate:product-costs';
    protected $description = 'Asociar costos desde dump externo a los productos existentes, mapeando id_product → id_wc';

    public function handle()
    {
        $this->info("Iniciando migración de costos...");

        $productsCsv = storage_path('app/products.csv'); // dump products
        $costsCsv = storage_path('app/products_costs.csv'); // dump costs

        // 1️⃣ Leer products.csv para mapear id del dump → id_wc
        $productsReader = Reader::createFromPath($productsCsv, 'r');
        $productsReader->setHeaderOffset(0);
        $productsRecords = (new Statement())->process($productsReader);

        $productMap = [];

        foreach ($productsRecords as $prod) {
            $dumpId = (int) $this->clean($prod['id'] ?? $prod['`id`']);
            $realId = (int) $this->clean($prod['id_wc'] ?? $prod['`id_wc`']);
            if ($dumpId && $realId) {
                $productMap[$dumpId] = $realId;
            }
        }

        // 2️⃣ Leer products_costs.csv
        $costsReader = Reader::createFromPath($costsCsv, 'r');
        $costsReader->setHeaderOffset(0);
        $costsRecords = (new Statement())->process($costsReader);

        $inserted = 0;

        foreach ($costsRecords as $record) {
            $dumpId = (int) $this->clean($record['id_product'] ?? $record['`id_product`']);

            if (!$dumpId || !isset($productMap[$dumpId])) {
                $this->warn("Producto dump ID $dumpId no encontrado en products.csv, se omite.");
                continue;
            }

            $realProductId = (int) $productMap[$dumpId];
            $costId = (int) $this->clean($record['id_cost'] ?? $record['`id_cost`']);

            DB::table('cost_product')->updateOrInsert(
                [
                    'product_id' => $realProductId,
                    'cost_id' => $costId,
                ],
                [
                    'created_at' => $this->parseDate($record['created_at'] ?? $record['`created_at`']) ?: Carbon::now(),
                    'updated_at' => $this->parseDate($record['updated_at'] ?? $record['`updated_at`']),
                ]
            );

            $inserted++;
        }

        $this->info("Migración de costos completada. Total insertados: $inserted ✅");
    }

    private function parseDate($value)
    {
        $value = trim($value);
        if (!$value)
            return null;

        // Si ya tiene formato correcto, lo dejamos
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        // Si viene sin dos puntos, ej: 2023-08-30 163941
        if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2})(\d{2})(\d{2})$/', $value, $matches)) {
            return "{$matches[1]} {$matches[2]}:{$matches[3]}:{$matches[4]}";
        }

        // fallback
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }


    private function clean($value)
    {
        return is_string($value) ? trim(str_replace(':', '', $value)) : $value;
    }
}
