<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ClientType;
use App\Models\GeneralStatus;
use App\Models\ClientWholesale;
use App\Models\ClientAddress;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Client extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_type_id',
        'name',
        'lastName',
        'email',
        'password',
        'phone',
        'status_id',
        'cuit',
    ];

    protected $hidden = [
        'password',
        'remember_token', // si lo usás
    ];

    public function clientType()
    {
        return $this->belongsTo(ClientType::class, 'client_type_id');
    }

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class);
    }

    public function wholesales()
    {
        return $this->hasMany(ClientWholesale::class);
    }

    public function addresses()
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastName' => $this->lastname,
            'email' => $this->email,
            'photo' => $this->photo,
            'clientTypeId' => $this->client_type_id
        ];
    }
}
