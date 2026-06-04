<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Channel;

class StockMovement extends Model
{
    protected $table = 'stock_movements';

    // Sin updated_at — registro append-only
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'quantity',
        'note',
        'user_id',
        'sale_id',
        'channel_id',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement) {
            $movement->created_at = now();
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
