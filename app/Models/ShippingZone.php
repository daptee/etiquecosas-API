<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $table = 'shipping_zones';

    protected $fillable = [
        'name',
        'postal_codes',
        'status_id',
    ];

    protected $casts = [
        'postal_codes' => 'array', // Para que se maneje como array en Laravel
    ];

    public function options()
    {
        return $this->hasMany(ShippingOption::class, 'zone_id');
    }

    public function status()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }
}
