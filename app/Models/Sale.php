<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{

    protected $table = 'sales';

    protected $fillable = [
        'client_id',
        'channel_id',
        'external_id',
        'address',
        'locality_id',
        'postal_code',
        'client_shipping_id',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'total',
        'payment_method_id',
        'shipping_cost',
        'shipping_method_id',
        'customer_notes',
        'internal_comments',
        'sale_status_id',
        'sale_id',
        'user_id',
        'cadete_id',
        'receiver_name',
        'receiver_dni',
        'receiver_observations',
        'delivered_at'
    ];

    // 🔹 Relaciones
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cadete()
    {
        return $this->belongsTo(User::class, 'cadete_id');
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function status()
    {
        return $this->belongsTo(SaleStatus::class, 'sale_status_id');
    }

    public function parentSale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function childSales()
    {
        return $this->hasMany(Sale::class, 'sale_id');
    }

    public function products()
    {
        return $this->hasMany(SaleProduct::class, 'sale_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(SaleStatusHistory::class, 'sale_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function locality()
    {
        return $this->belongsTo(Locality::class, 'locality_id');
    }

    /**
     * Verifica si la venta ya fue aprobada (status 1) alguna vez en su historial
     *
     * @return bool
     */
    public function hasBeenApproved()
    {
        return $this->statusHistory()
            ->where('sale_status_id', 1)
            ->exists();
    }
}
