<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Client;

class ClientShipping extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'address',
        'locality_id',
        'postal_code',
        'observations'
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
