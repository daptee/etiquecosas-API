<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductClientExclusion extends Model
{
    protected $fillable = [
        'product_id',
        'client_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
