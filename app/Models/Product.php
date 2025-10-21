<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ProductType;
use App\Models\ProductStatus;
use App\Models\ProductStockStatus;
use App\Models\ProductWholesale;
use App\Models\ConfigurationTag;
use App\Models\Category;
use App\Models\Cost;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use App\Models\ProductCustomization;
use App\Models\ProductImage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'slug',
        'name',
        'product_type_id',
        'product_status_id',
        'price',
        'discounted_price',
        'discounted_start',
        'discounted_end',
        'product_stock_status_id',
        'stock_quantity',
        'wholesale_price',
        'wholesale_min_amount',
        'tag_id',
        'costs',
        'description',
        'shortDescription',
        'shipping_text',
        'shipping_time_text',
        'notifications_text',
        'tutorial_link',
        'is_feature',
        'is_customizable',
        'is_sale',
        'meta_data',
    ];

    protected $casts = [
        'costs' => 'array',
        'meta_data' => 'array',
        'is_feature' => 'integer',
        'is_customizable' => 'integer',
        'price' => 'float',
        'discounted_price' => 'float',
        'wholesale_price' => 'float', 
        'wholesale_min_amount' => 'integer',
        'stock_quantity' => 'integer',
    ];

    public function type()
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function status()
    {
        return $this->belongsTo(ProductStatus::class, 'product_status_id');
    }

    public function stockStatus()
    {
        return $this->belongsTo(ProductStockStatus::class, 'product_stock_status_id');
    }

    public function tag()
    {
        return $this->belongsTo(ConfigurationTag::class, 'tag_id');
    }

    public function wholesales()
    {
        return $this->hasMany(ProductWholesale::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function costs()
    {
        return $this->belongsToMany(Cost::class);
    }    

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class);
    }

    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function customization()
    {
        return $this->hasOne(ProductCustomization::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function relatedProducts()
    {
        return $this->belongsToMany(Product::class, 'product_related_product', 'product_id', 'related_product_id');
    }
}
