<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class ProductWholesale extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'amount',
        'discount',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
