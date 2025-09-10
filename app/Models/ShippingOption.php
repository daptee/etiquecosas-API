<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingOption extends Model
{
    use HasFactory;

    protected $table = 'shipping_options';

    protected $fillable = [
        'zone_id',
        'name',
        'price',
        'status_id',
    ];

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }

    public function status()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }
}
