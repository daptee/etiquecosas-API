<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ClientType;
use App\Models\GeneralStatus;
use App\Models\ClientShipping;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_type_id',
        'name',
        'lastname',
        'email',
        'password',
        'phone',
        'billing_data',
        'wholesale_data',
        'status_id'
    ];

    public function type()
    {
        return $this->belongsTo(ClientType::class, 'client_type_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function shippings()
    {
        return $this->hasMany(ClientShipping::class);
    }
}
