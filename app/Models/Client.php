<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ClientType;
use App\Models\GeneralStatus;
use App\Models\ClientShipping;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_type_id',
        'name',
        'lastName',
        'email',
        'password',
        'phone',
        'billing_data',
        'wholesale_data',
        'status_id'
    ];

    public function clientType()
    {
        return $this->belongsTo(ClientType::class, 'client_type_id');
    }

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class);
    }

    public function shippings()
    {
        return $this->hasMany(ClientShipping::class);
    }
}
