<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Coupon;

class CouponStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }
}
