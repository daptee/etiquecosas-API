<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleProduct extends Model
{
    protected $table = 'sales_products';

    protected $fillable = [
        'sale_id',
        'product_id',
        'variant_id',
        'customization_data',
        'quantity',
        'unit_price',
        'comment',
    ];

    protected $casts = [
        'customization_data' => 'array',
        'variants' => 'array',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
