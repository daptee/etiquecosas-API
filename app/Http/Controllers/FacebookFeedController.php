<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;

class FacebookFeedController extends Controller
{
    /**
     * Generate XML feed for Facebook/Meta catalog
     *
     * @return Response
     */
    public function generateFeed()
    {
        // Get all products (not soft deleted)
        // Only exclude soft deleted products, include all statuses
        $products = Product::with(['images', 'status', 'stockStatus'])
            ->get();

        // Start building XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss xmlns:g="http://base.google.com/ns/1.0" version="2.0"></rss>');

        $channel = $xml->addChild('channel');
        $channel->addChild('title', 'Catálogo Etiquecosas');
        $channel->addChild('link', 'https://etiquecosas.ar');
        $channel->addChild('description', 'Productos del sitio Etiquecosas');

        foreach ($products as $product) {
            $item = $channel->addChild('item');

            // g:id - Product ID (same as Pixel content_ids)
            $item->addChild('g:id', $product->id, 'http://base.google.com/ns/1.0');

            // g:title - Product name
            $item->addChild('g:title', htmlspecialchars($product->name, ENT_XML1, 'UTF-8'), 'http://base.google.com/ns/1.0');

            // g:description - Product description (use shortDescription or description)
            $description = $product->shortDescription ?? $product->description ?? '';
            // Clean and limit description
            $description = strip_tags($description);
            $description = mb_substr($description, 0, 5000); // Meta recommends max 5000 chars
            $item->addChild('g:description', htmlspecialchars($description, ENT_XML1, 'UTF-8'), 'http://base.google.com/ns/1.0');

            // g:link - Product URL
            $productUrl = 'https://www.etiquecosas.com.ar/productos/' . $product->slug;
            $item->addChild('g:link', htmlspecialchars($productUrl, ENT_XML1, 'UTF-8'), 'http://base.google.com/ns/1.0');

            // g:image_link - Main product image
            $imageUrl = $this->getProductImageUrl($product);
            if ($imageUrl) {
                $item->addChild('g:image_link', htmlspecialchars($imageUrl, ENT_XML1, 'UTF-8'), 'http://base.google.com/ns/1.0');
            }

            // g:availability - Stock status
            $availability = $this->getAvailability($product);
            $item->addChild('g:availability', $availability, 'http://base.google.com/ns/1.0');

            // g:price - Product price with currency
            $price = $this->getPrice($product);
            $item->addChild('g:price', $price, 'http://base.google.com/ns/1.0');

            // g:brand - Brand name
            $item->addChild('g:brand', 'Etiquecosas', 'http://base.google.com/ns/1.0');

            // Optional: g:condition - Product condition
            $item->addChild('g:condition', 'new', 'http://base.google.com/ns/1.0');
        }

        // Get XML content
        $xmlContent = $xml->asXML();

        // Save XML to public directory
        $publicPath = public_path('feed-facebook.xml');
        file_put_contents($publicPath, $xmlContent);

        // Return XML response
        return response($xmlContent, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Get product main image URL
     *
     * @param Product $product
     * @return string|null
     */
    private function getProductImageUrl(Product $product): ?string
    {
        // Get the main image or first image from the product images relation
        if ($product->images && $product->images->count() > 0) {
            // Try to get the main image first
            $mainImage = $product->images->where('is_main', true)->first();
            $image = $mainImage ?? $product->images->first();

            // Return full URL to the image
            if (isset($image->img) && !empty($image->img)) {
                // Check if it's already a full URL
                if (str_starts_with($image->img, 'http://') || str_starts_with($image->img, 'https://')) {
                    return $image->img;
                }

                // Build full URL using the API public images path
                return 'https://api.etiquecosas.com.ar/public/' . $image->img;
            }
        }

        // Return default image if no image found
        return 'https://etiquecosas.ar/img/default-product.jpg';
    }

    /**
     * Get product availability status
     *
     * @param Product $product
     * @return string
     */
    private function getAvailability(Product $product): string
    {
        // Check stock status
        if ($product->stockStatus) {
            // Adjust these conditions based on your stock status IDs
            // Common mapping: 1 = in stock, 2 = out of stock, 3 = preorder
            if ($product->product_stock_status_id == 1) {
                return 'in stock';
            } elseif ($product->product_stock_status_id == 2) {
                return 'out of stock';
            } elseif ($product->product_stock_status_id == 3) {
                return 'preorder';
            }
        }

        // Fallback: check stock_quantity
        if (isset($product->stock_quantity) && $product->stock_quantity > 0) {
            return 'in stock';
        }

        return 'in stock'; // Default to in stock for digital/customizable products
    }

    /**
     * Get product price with currency
     *
     * @param Product $product
     * @return string
     */
    private function getPrice(Product $product): string
    {
        // Use discounted price if available and valid
        if ($product->discounted_price && $product->discounted_price > 0) {
            // Check if discount is currently active
            $now = now();
            $isDiscountActive = true;

            if ($product->discounted_start) {
                $isDiscountActive = $isDiscountActive && $now->gte($product->discounted_start);
            }

            if ($product->discounted_end) {
                $isDiscountActive = $isDiscountActive && $now->lte($product->discounted_end);
            }

            if ($isDiscountActive) {
                return number_format($product->discounted_price, 2, '.', '') . ' ARS';
            }
        }

        // Use regular price
        $price = $product->price ?? 0;
        return number_format($price, 2, '.', '') . ' ARS';
    }
}
