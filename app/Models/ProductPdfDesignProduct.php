<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPdfDesignProduct extends Model
{
    protected $table = 'product_pdf_design_products';

    protected $fillable = [
        'product_pdf_design_id',
        'product_id',
        'theme_key',
    ];

    public function design()
    {
        return $this->belongsTo(ProductPdfDesign::class, 'product_pdf_design_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
