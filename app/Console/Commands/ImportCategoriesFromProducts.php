<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class ImportCategoriesFromProducts extends Command
{
    protected $signature = 'categories:import-from-products';
    protected $description = 'Importa categorías y subcategorías desde products.shipping_text y relaciona con products';

    public function handle()
    {
        $products = Product::whereNotNull('shipping_text')->get();

        foreach ($products as $product) {
            $categoryTexts = explode(',', $product->shipping_text);

            foreach ($categoryTexts as $categoryPath) {
                $categoryPath = trim($categoryPath);

                // Dividir por jerarquía
                $levels = array_map('trim', explode('>', $categoryPath));

                $parentId = null;
                $lastCategory = null;

                foreach ($levels as $levelName) {
                    // Buscar categoría
                    $category = Category::where('name', $levelName)
                        ->where('category_id', $parentId)
                        ->first();

                    if (!$category) {
                        $category = Category::create([
                            'name' => $levelName,
                            'category_id' => $parentId,
                            'status_id' => 1, // Ajusta según tu lógica
                        ]);

                        $this->info("✅ Creada categoría: {$levelName} (Padre: {$parentId})");
                    }

                    $parentId = $category->id;
                    $lastCategory = $category;
                }

                // ✅ Reglas de relación
                if (count($levels) > 1) {
                    // Relacionar solo al último nivel
                    $this->attachCategory($product->id, $lastCategory->id);
                } else {
                    // Relacionar al padre único
                    $this->attachCategory($product->id, $lastCategory->id);
                }
            }
        }

        $this->info('🎉 Importación de categorías y relaciones completada.');
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
