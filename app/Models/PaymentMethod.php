<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{

    protected $table = 'payment_methods';

    protected $fillable = [
        'name',
        'description',
        'status_id'
    ];

    public function status()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }
}
