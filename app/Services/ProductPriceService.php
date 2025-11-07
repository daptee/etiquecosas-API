<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class ProductPriceService
{
    /**
     * Calcula el porcentaje de ganancia basado en precio y costos totales
     *
     * @param float $price Precio del producto
     * @param float $totalCosts Suma de costos
     * @return float|null Porcentaje de ganancia o null si no se puede calcular
     */
    public function calculateProfitPercentage(float $price, float $totalCosts): ?float
    {
        if ($totalCosts <= 0 || $price <= 0) {
            return null;
        }

        return (($price - $totalCosts) / $totalCosts) * 100;
    }

    /**
     * Calcula el porcentaje de descuento basado en precio normal y precio de oferta
     *
     * @param float $regularPrice Precio normal
     * @param float|null $discountedPrice Precio de oferta
     * @return float|null Porcentaje de descuento o null si no se puede calcular
     */
    public function calculateDiscountPercentage(float $regularPrice, ?float $discountedPrice): ?float
    {
        if (!$discountedPrice || $regularPrice <= 0) {
            return null;
        }

        return (($regularPrice - $discountedPrice) / $regularPrice) * 100;
    }

    /**
     * Calcula el precio basado en costos y porcentaje de ganancia
     *
     * @param float $totalCosts Suma de costos
     * @param float|null $profitPercentage Porcentaje de ganancia
     * @return float|null Precio calculado o null si no se puede calcular
     */
    public function calculatePriceFromCosts(float $totalCosts, ?float $profitPercentage): ?float
    {
        if ($totalCosts <= 0 || $profitPercentage === null) {
            return null;
        }

        return $totalCosts * (1 + $profitPercentage / 100);
    }

    /**
     * Calcula el precio de oferta basado en precio normal y porcentaje de descuento
     *
     * @param float $regularPrice Precio normal
     * @param float|null $discountPercentage Porcentaje de descuento
     * @return float|null Precio de oferta calculado o null si no se puede calcular
     */
    public function calculateDiscountedPrice(float $regularPrice, ?float $discountPercentage): ?float
    {
        if ($regularPrice <= 0 || $discountPercentage === null) {
            return null;
        }

        return $regularPrice * (1 - $discountPercentage / 100);
    }

    /**
     * Actualiza los precios de un producto basándose en sus costos y porcentajes
     *
     * @param Product $product Producto a actualizar
     * @param bool $updateVariants Si se deben actualizar también las variantes
     * @return void
     */
    public function updateProductPrices(Product $product, bool $updateVariants = true): void
    {
        // Cargar costos si no están cargados
        if (!$product->relationLoaded('costs')) {
            $product->load('costs');
        }

        // Calcular suma de costos
        $totalCosts = $product->costs->sum('price');

        // Actualizar precio normal si hay porcentaje de ganancia
        if ($product->profit_percentage !== null && $totalCosts > 0) {
            $newPrice = $this->calculatePriceFromCosts($totalCosts, $product->profit_percentage);

            if ($newPrice !== null) {
                $product->price = round($newPrice, 2);

                // Actualizar precio de oferta si hay porcentaje de descuento
                if ($product->discount_percentage !== null) {
                    $newDiscountedPrice = $this->calculateDiscountedPrice($product->price, $product->discount_percentage);

                    if ($newDiscountedPrice !== null) {
                        $product->discounted_price = round($newDiscountedPrice, 2);
                    }
                }
            }
        }

        $product->save();

        // Actualizar variantes si es necesario
        if ($updateVariants && $product->relationLoaded('variants')) {
            $this->updateVariantsPrices($product, $totalCosts);
        } elseif ($updateVariants) {
            $product->load('variants');
            $this->updateVariantsPrices($product, $totalCosts);
        }
    }

    /**
     * Actualiza los precios de las variantes de un producto
     *
     * @param Product $product Producto padre
     * @param float $totalCosts Suma de costos del producto
     * @return void
     */
    public function updateVariantsPrices(Product $product, float $totalCosts): void
    {
        foreach ($product->variants as $variant) {
            $variantData = $variant->variant;

            if (!$variantData) {
                continue;
            }

            $variantProfitPercentage = $variantData['profit_percentage'] ?? null;
            $variantDiscountPercentage = $variantData['discount_percentage'] ?? null;

            // Actualizar precio normal de la variante
            if ($variantProfitPercentage !== null && $totalCosts > 0) {
                $newPrice = $this->calculatePriceFromCosts($totalCosts, $variantProfitPercentage);

                if ($newPrice !== null) {
                    $variantData['price'] = round($newPrice, 2);

                    // Actualizar precio de oferta de la variante
                    if ($variantDiscountPercentage !== null) {
                        $newDiscountedPrice = $this->calculateDiscountedPrice($variantData['price'], $variantDiscountPercentage);

                        if ($newDiscountedPrice !== null) {
                            $variantData['discounted_price'] = round($newDiscountedPrice, 2);
                        }
                    }
                }
            }

            $variant->variant = $variantData;
            $variant->save();
        }
    }

    /**
     * Actualiza todos los productos que usan un costo específico
     *
     * @param int $costId ID del costo que cambió
     * @return int Número de productos actualizados
     */
    public function updateProductsUsingCost(int $costId): int
    {
        $products = Product::whereHas('costs', function ($query) use ($costId) {
            $query->where('costs.id', $costId);
        })->with('costs', 'variants')->get();

        $updatedCount = 0;

        foreach ($products as $product) {
            try {
                $this->updateProductPrices($product);
                $updatedCount++;

                Log::info("Precio actualizado para producto: {$product->name} (ID: {$product->id})");
            } catch (\Exception $e) {
                Log::error("Error actualizando producto {$product->id}: " . $e->getMessage());
            }
        }

        return $updatedCount;
    }

    /**
     * Calcula y guarda los porcentajes de un producto basándose en sus precios actuales
     *
     * @param Product $product Producto
     * @param array|null $variantsData Datos de variantes si se están actualizando
     * @return void
     */
    public function calculateAndSavePercentages(Product $product, ?array $variantsData = null): void
    {
        // Cargar costos si no están cargados
        if (!$product->relationLoaded('costs')) {
            $product->load('costs');
        }

        $totalCosts = $product->costs->sum('price');

        // Calcular porcentajes para el producto principal
        $product->profit_percentage = $this->calculateProfitPercentage($product->price, $totalCosts);
        $product->discount_percentage = $this->calculateDiscountPercentage($product->price, $product->discounted_price);

        // Si se proporcionan datos de variantes, calcular sus porcentajes también
        if ($variantsData !== null && is_array($variantsData)) {
            foreach ($variantsData as &$variantData) {
                $variantPrice = $variantData['price'] ?? null;
                $variantDiscountedPrice = $variantData['discounted_price'] ?? null;

                if ($variantPrice) {
                    $variantData['profit_percentage'] = $this->calculateProfitPercentage($variantPrice, $totalCosts);
                    $variantData['discount_percentage'] = $this->calculateDiscountPercentage($variantPrice, $variantDiscountedPrice);
                }
            }
        }
    }
}
