<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateProductsFromSql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan update:products-sql path/to/file.sql
     */
    protected $signature = 'update:products-sql {file : Path to the SQL file}';

    /**
     * The console command description.
     */
    protected $description = 'Lee un archivo SQL con INSERTS y actualiza shipping_text en products según el id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("El archivo no existe: $filePath");
            return Command::FAILURE;
        }

        $sqlContent = file_get_contents($filePath);

        // Busca los INSERT con regex
        preg_match_all(
            "/INSERT INTO products\( id,shipping_text \)\s*VALUES\s*\(\s*([0-9]+),'(.*?)'\s*\);/s",
            $sqlContent,
            $matches,
            PREG_SET_ORDER
        );

        if (empty($matches)) {
            $this->error("No se encontraron INSERTS válidos en el archivo.");
            return Command::FAILURE;
        }

        foreach ($matches as $match) {
            $id = $match[1];
            $shippingText = $match[2];

            // Limpia saltos de línea escapados
            $shippingText = str_replace(["\\n", "\\r"], ["\n", ""], $shippingText);

            DB::table('products')
                ->where('id', $id)
                ->update(['shipping_text' => $shippingText]);

            $this->info("Actualizado producto ID: $id");
        }

        $this->info("✅ Proceso completado.");
        return Command::SUCCESS;
    }
}
