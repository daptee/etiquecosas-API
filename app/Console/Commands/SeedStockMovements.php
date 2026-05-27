<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Console\Command;

class SeedStockMovements extends Command
{
    protected $signature   = 'stock:seed-movements';
    protected $description = 'Crea registros iniciales en stock_movements para todos los productos/variantes existentes. Ejecutar UNA SOLA VEZ al momento de implementar el historial.';

    public function handle(): int
    {
        $this->info('Iniciando seed de movimientos de stock iniciales...');

        $note = 'Stock inicial al momento de implementación del historial';

        // --- Productos ---
        Product::chunk(100, function ($products) use ($note) {
            foreach ($products as $product) {
                $stockChannels = $product->stock_channels ?? [];

                if (empty($stockChannels)) {
                    $this->line("  Producto ID {$product->id} sin stock_channels — omitido.");
                    continue;
                }

                foreach ($stockChannels as $channel) {
                    $qty = (int) ($channel['stock_quantity'] ?? 0);

                    if ($qty <= 0) {
                        continue;
                    }

                    StockMovement::create([
                        'product_id'         => $product->id,
                        'product_variant_id' => null,
                        'quantity'           => $qty,
                        'note'               => $note,
                        'user_id'            => null,
                        'sale_id'            => null,
                    ]);

                    $channelName = $channel['channel_name'] ?? ('Canal ' . $channel['channel']);
                    $this->line("  Producto ID {$product->id} ({$product->name}) — {$channelName}: +{$qty} registrado.");
                }
            }
        });

        // --- Variantes ---
        ProductVariant::chunk(100, function ($variants) use ($note) {
            foreach ($variants as $variant) {
                $stockChannels = $variant->stock_channels ?? [];

                if (empty($stockChannels)) {
                    $this->line("  Variante ID {$variant->id} sin stock_channels — omitida.");
                    continue;
                }

                foreach ($stockChannels as $channel) {
                    $qty = (int) ($channel['stock_quantity'] ?? 0);

                    if ($qty <= 0) {
                        continue;
                    }

                    StockMovement::create([
                        'product_id'         => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'quantity'           => $qty,
                        'note'               => $note,
                        'user_id'            => null,
                        'sale_id'            => null,
                    ]);

                    $channelName = $channel['channel_name'] ?? ('Canal ' . $channel['channel']);
                    $this->line("  Variante ID {$variant->id} (Producto ID {$variant->product_id}) — {$channelName}: +{$qty} registrado.");
                }
            }
        });

        $this->info('Seed de movimientos de stock finalizado correctamente.');
        $this->warn('IMPORTANTE: Este comando no es idempotente. Ejecutarlo nuevamente duplicará los registros.');

        return Command::SUCCESS;
    }
}
