<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductType;
use App\Models\ProductStatus;
use App\Models\ProductStockStatus;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'product_type_id',
        'product_status_id',
        'price',
        'discounted_price',
        'product_stock_status_id',
        'stock_quantity',
        'tag',
        'description',
        'is_feature',
        'tutorial_link',
        'is_customizable',
        'meta_data',
    ];

    protected $casts = [
        'is_feature' => 'boolean',
        'is_customizable' => 'boolean',
        'meta_data' => 'array',
    ];

    public function type()
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function generalStatus()
    {
        return $this->belongsTo(ProductStatus::class, 'product_status_id');
    }

    public function stockStatus()
    {
        return $this->belongsTo(ProductStockStatus::class, 'product_stock_status_id');
    }
}
