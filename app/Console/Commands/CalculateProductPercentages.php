<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\ProductVariant;

class CalculateProductPercentages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:calculate-percentages {--dry-run : Show what would be updated without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcular y asignar porcentajes de ganancia y descuento a productos y variantes existentes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Modo DRY RUN - No se guardarán cambios');
        }

        $this->info('Procesando productos...');
        $products = Product::with('costs', 'variants')->get();
        $productsUpdated = 0;
        $variantsUpdated = 0;

        foreach ($products as $product) {
            // Asegurar que los costos estén cargados
            if (!$product->relationLoaded('costs')) {
                $product->load('costs');
            }

            // Calcular suma de costos
            $totalCosts = $product->costs ? $product->costs->sum('price') : 0;

            // Calcular porcentaje de ganancia
            $profitPercentage = null;
            if ($totalCosts > 0 && $product->price > 0) {
                $profitPercentage = (($product->price - $totalCosts) / $totalCosts) * 100;
            }

            // Calcular porcentaje de descuento
            $discountPercentage = null;
            if ($product->discounted_price && $product->price > 0) {
                $discountPercentage = (($product->price - $product->discounted_price) / $product->price) * 100;
            }

            // Actualizar el producto
            if (!$dryRun) {
                $product->profit_percentage = $profitPercentage;
                $product->discount_percentage = $discountPercentage;
                $product->save();
            }

            $productsUpdated++;

            $this->line("Producto: {$product->name} (SKU: {$product->sku})");
            $this->line("  Precio: {$product->price}, Costos: {$totalCosts}");
            $this->line("  Ganancia: " . ($profitPercentage ? round($profitPercentage, 2) . '%' : 'N/A'));
            $this->line("  Descuento: " . ($discountPercentage ? round($discountPercentage, 2) . '%' : 'N/A'));

            // Procesar variantes
            foreach ($product->variants as $variant) {
                $variantData = $variant->variant;

                if (!$variantData) {
                    continue;
                }

                // Calcular porcentaje de ganancia de la variante
                $variantProfitPercentage = null;
                $variantPrice = $variantData['price'] ?? null;

                if ($totalCosts > 0 && $variantPrice > 0) {
                    $variantProfitPercentage = (($variantPrice - $totalCosts) / $totalCosts) * 100;
                }

                // Calcular porcentaje de descuento de la variante
                $variantDiscountPercentage = null;
                $variantDiscountedPrice = $variantData['discounted_price'] ?? null;

                if ($variantDiscountedPrice && $variantPrice > 0) {
                    $variantDiscountPercentage = (($variantPrice - $variantDiscountedPrice) / $variantPrice) * 100;
                }

                // Actualizar la variante
                if (!$dryRun) {
                    $variantData['profit_percentage'] = $variantProfitPercentage;
                    $variantData['discount_percentage'] = $variantDiscountPercentage;
                    $variant->variant = $variantData;
                    $variant->save();
                }

                $variantsUpdated++;

                $variantName = $variantData['name'] ?? 'Sin nombre';
                $variantSku = $variantData['sku'] ?? 'N/A';
                $this->line("  Variante: {$variantName} (SKU: {$variantSku})");
                $this->line("    Precio: {$variantPrice}, Costos: {$totalCosts}");
                $this->line("    Ganancia: " . ($variantProfitPercentage ? round($variantProfitPercentage, 2) . '%' : 'N/A'));
                $this->line("    Descuento: " . ($variantDiscountPercentage ? round($variantDiscountPercentage, 2) . '%' : 'N/A'));
            }

            $this->newLine();
        }

        $this->info("Resumen:");
        $this->info("  Productos procesados: {$productsUpdated}");
        $this->info("  Variantes procesadas: {$variantsUpdated}");

        if ($dryRun) {
            $this->warn('No se guardaron cambios (modo dry-run)');
        } else {
            $this->info('¡Porcentajes calculados y guardados exitosamente!');
        }

        return 0;
    }
}
