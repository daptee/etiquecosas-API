<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class ProductPdf extends Model
{
    protected $table = 'product_pdf';

    protected $fillable = [
        'product_id',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
