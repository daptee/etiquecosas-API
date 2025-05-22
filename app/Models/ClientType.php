<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Client;

class ClientType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
