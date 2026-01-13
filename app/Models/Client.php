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
        'business_name',
    ];

    protected $hidden = [
        'password',
        'remember_token', // si lo usás
    ];

    protected $appends = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Prepare a date for array / JSON serialization.
     */
    public function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Accessor para lastName en formato camelCase
     */
    public function getLastNameAttribute($value)
    {
        return $value;
    }

    /**
     * Override toArray para asegurar que lastName se serialice en camelCase
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Si existe 'last_name' (snake_case), renombrarlo a 'lastName' (camelCase)
        if (isset($array['last_name']) && !isset($array['lastName'])) {
            $array['lastName'] = $array['last_name'];
            unset($array['last_name']);
        }

        return $array;
    }

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
