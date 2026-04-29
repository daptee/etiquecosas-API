<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\CouponStatus;
use App\Models\Category;
use App\Models\Product;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'date_from',
        'date_to',
        'min_amount',
        'type',
        'applies_to_all_products',
        'applies_to_shipping',
        'applies_to_web',
        'applies_to_sale_price',
        'max_use_per_user',
        'max_use_per_code',
        'coupon_status_id',
        'value',
        'tiered_discounts_enabled',
        'tiered_discounts',
        'flash_enabled',
    ];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
        'applies_to_shipping' => 'boolean',
        'applies_to_all_products' => 'boolean',
        'applies_to_web' => 'boolean',
        'applies_to_sale_price' => 'boolean',
        'tiered_discounts_enabled' => 'boolean',
        'tiered_discounts' => 'array',
        'flash_enabled' => 'boolean',
    ];

    public function status()
    {
        return $this->belongsTo(CouponStatus::class, 'coupon_status_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'coupon_id');
    }

    public function salesMany()
    {
        return $this->belongsToMany(Sale::class, 'coupon_sale')
            ->withPivot('discount_amount')
            ->withTimestamps();
    }
}
