<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingConfig extends Model
{

    protected $table = 'shipping_config';

    protected $fillable = [
        'data',
    ];

    protected $casts = [
        'data' => 'array', // convierte automáticamente JSON a array al leer/escribir
    ];
}
