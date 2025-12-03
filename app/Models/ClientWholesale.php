<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Client;
use App\Models\Locality;

class ClientWholesale extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'locality_id',
        'address',
        'postal_code',
        'business_name',
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
