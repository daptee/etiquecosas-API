<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\ProductVariant;

class InitializeStockChannels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan stock:initialize-channels
     */
    protected $signature = 'stock:initialize-channels';

    /**
     * The console command description.
     */
    protected $description = 'Inicializa los canales de stock (online/web) para productos y variantes existentes, manteniendo el estado actual.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Iniciando proceso de inicialización de stock_channels...');

        // --- Productos ---
        Product::chunk(100, function ($products) {
            foreach ($products as $product) {
                if ($product->is_customizable == 0 && $product->product_type_id == 1) {
                    $product->stock_channels = [
                        [
                            'channel' => 1,
                            'channel_name' => 'Web',
                            'stock_status' => $product->stockStatus?->id ?? 1,
                            'stock_status_name' => $product->stockStatus?->name ?? 'Existente',
                            'stock_quantity' => $product->stock_quantity ?? 0,
                        ],
                        [
                            'channel' => 2,
                            'channel_name' => 'Local Comercial',
                            'stock_status' => $product->stockStatus?->id ?? 1,
                            'stock_status_name' => $product->stockStatus?->name ?? 'Existente',
                            'stock_quantity' => $product->stock_quantity ?? 0,
                        ],
                    ];
                    $product->save();

                    $this->line("✅ Producto ID {$product->id} actualizado con canales de stock.");

                } else {
                    $product->stock_channels = [
                        [
                            'channel' => 1,
                            'channel_name' => 'Web',
                            'stock_status' => $product->stockStatus?->id ?? 1,
                            'stock_status_name' => $product->stockStatus?->name ?? 'Existente',
                            'stock_quantity' => $product->stock_quantity ?? 0,
                        ],
                    ];
                    $product->save();



                    $this->line("✅ Producto ID {$product->id} actualizado con canales de stock.");
                }
            }
        });

        // --- Variantes ---
        ProductVariant::chunk(100, function ($variants) {
            foreach ($variants as $variant) {
                $variant->stock_channels = [
                    [
                        'channel' => 1,
                        'channel_name' => 'Web',
                        'stock_status' => $variant->variant['stock_status'] ?? 1,
                        'stock_status_name' => match ($variant->variant['stock_status'] ?? 1) {
                            "1" => 'Existente',
                            "2" => 'Gestión de Stock',
                            "3" => 'Sin Stock',
                            1 => 'Existente',
                            2 => 'Gestión de Stock',
                            3 => 'Sin Stock',
                            default => 'Existente',
                        },
                        'stock_quantity' => $variant->variant['stock_quantity'] ?? 0,
                    ],
                ];
                $variant->save();

                $this->line("✅ Variante ID {$variant->id} actualizada con canales de stock.");

            }
        });

        $this->info('🎉 Proceso de inicialización de stock_channels finalizado correctamente.');
        return Command::SUCCESS;
    }
}
