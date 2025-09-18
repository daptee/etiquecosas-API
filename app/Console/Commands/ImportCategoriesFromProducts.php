<?php 

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class ImportCategoriesFromProducts extends Command
{
    protected $signature = 'categories:import-from-products';
    protected $description = 'Importa categorías desde products.shipping_text y relaciona con products (sin crear nuevas categorías), soportando comas escapadas';

    public function handle()
    {
        $products = Product::whereNotNull('shipping_text')->get();

        foreach ($products as $product) {
            // Dividir por comas NO escapadas
            $categoryTexts = preg_split('/(?<!\\\\),/', $product->shipping_text);

            // Reemplazar comas escapadas '\,' por comas reales dentro del nombre
            $categoryTexts = array_map(function($text) {
                return trim(str_replace('\,', ',', $text));
            }, $categoryTexts);

            foreach ($categoryTexts as $categoryPath) {
                // Dividir jerarquía por ">"
                $levels = array_map('trim', explode('>', $categoryPath));

                $parentId = null;
                $lastCategory = null;

                foreach ($levels as $levelName) {
                    // Buscar categoría existente
                    $category = Category::where('name', $levelName)
                        ->where('category_id', $parentId)
                        ->first();

                    if (!$category) {
                        $this->warn("⚠️ Categoría no encontrada: {$levelName} (Padre: {$parentId}) en producto {$product->id}, se ignora este path.");
                        $lastCategory = null;
                        break; // salimos porque la jerarquía no existe completa
                    }

                    $parentId = $category->id;
                    $lastCategory = $category;
                }

                // ✅ Reglas de relación
                if ($lastCategory) {
                    if (count($levels) > 1) {
                        // Relacionar solo al último nivel
                        $this->attachCategory($product->id, $lastCategory->id);
                    } else {
                        // Relacionar al padre único
                        $this->attachCategory($product->id, $lastCategory->id);
                    }
                }
            }
        }

        $this->info('🎉 Importación de relaciones completada (sin crear categorías nuevas).');
    }

    private function attachCategory($productId, $categoryId)
    {
        // Evitar duplicados
        $exists = DB::table('category_product')
            ->where('product_id', $productId)
            ->where('category_id', $categoryId)
            ->exists();

        if (!$exists) {
            DB::table('category_product')->insert([
                'product_id' => $productId,
                'category_id' => $categoryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("🔗 Relacionado producto {$productId} con categoría {$categoryId}");
        }
    }
}
