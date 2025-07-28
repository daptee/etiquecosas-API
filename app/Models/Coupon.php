<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\CouponStatus;

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
        'applies_to_shipping',
        'max_use_per_user',
        'max_use_per_code',
        'coupon_status_id',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'applies_to_shipping' => 'boolean',
    ];

    public function status(): BelongsTo
    {
        return $this->belongsTo(CouponStatus::class, 'coupon_status_id');
    }
}
