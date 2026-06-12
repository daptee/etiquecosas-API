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
use App\Models\Client;

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
        'profit_percentage',
        'discounted_price',
        'discount_percentage',
        'discounted_start',
        'discounted_end',
        'product_stock_status_id',
        'stock_quantity',
        'stock_alert',
        'stock_channels',
        'wholesale_price',
        'wholesale_min_amount',
        'tag_id',
        'description',
        'shortDescription',
        'shipping_text',
        'shipping_time_text',
        'notifications_text',
        'tutorial_link',
        'is_feature',
        'is_customizable',
        'is_sale',
        'is_wholesale',
        'wholesale_hidden',
        'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_feature' => 'integer',
        'is_customizable' => 'integer',
        'price' => 'float',
        'profit_percentage' => 'float',
        'discounted_price' => 'float',
        'discount_percentage' => 'float',
        'wholesale_price' => 'float',
        'wholesale_min_amount' => 'integer',
        'stock_quantity' => 'integer',
        'stock_alert' => 'integer',
        'stock_channels' => 'array',
        'wholesale_hidden' => 'boolean',
    ];

    public function toArray()
    {
        $array = parent::toArray();
        if (!empty($array['stock_channels'])) {
            $array['stock_channels'] = array_map(function ($ch) {
                if (isset($ch['is_heritable'])) {
                    $ch['is_heritable'] = (int) $ch['is_heritable'];
                }
                return $ch;
            }, $array['stock_channels']);
        }
        return $array;
    }

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
        return $this->belongsToMany(Attribute::class)->withPivot('order')->orderByPivot('order');
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
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function relatedProducts()
    {
        return $this->belongsToMany(Product::class, 'product_related_product', 'product_id', 'related_product_id');
    }

    public function excludedClients()
    {
        return $this->belongsToMany(Client::class, 'product_client_exclusions');
    }
}
