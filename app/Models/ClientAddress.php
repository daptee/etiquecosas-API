<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Client;
use App\Models\Locality;

class ClientAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'locality_id',
        'address',
        'locality_id',
        'postal_code',
        'observations',
        'name',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function locality()
    {
        return $this->belongsTo(Locality::class);
    }
}
