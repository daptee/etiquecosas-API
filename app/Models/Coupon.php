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
        'max_use_per_user',
        'max_use_per_code',
        'coupon_status_id',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'applies_to_shipping' => 'boolean',
        'applies_to_all_products' => 'boolean',
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
}
