<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Product;

class GenerateSkuSlug extends Command
{
    protected $signature = 'products:generate-sku-slug';
    protected $description = 'Genera SKU y SLUG para todos los productos';

    public function handle()
    {
        $products = Product::all();

        foreach ($products as $product) {
            $words = explode(' ', $product->name);
            $initials = '';
            foreach ($words as $word) {
                $initials .= strtoupper(mb_substr($word, 0, 1));
            }
            $product->sku = $initials . '-' . $product->id;
            $product->slug = Str::slug($product->name) . '-' . $product->id;
            $product->save();
        }

        $this->info('SKU y SLUG generados correctamente ✅');
    }
}
