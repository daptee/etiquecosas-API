<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPdfDesign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'label_shape_id',
        'name',
        'data',
        'is_published',
        'status_id',
    ];

    protected $casts = [
        'data' => 'array',
        'is_published' => 'boolean',
    ];

    public function labelShape()
    {
        return $this->belongsTo(LabelShape::class);
    }

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    /**
     * Productos que usan este diseño. Cada vínculo tiene su propio theme_key
     * (la variante/temática que lo selecciona en ese producto puntual).
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_pdf_design_products')
            ->withPivot('id', 'theme_key')
            ->withTimestamps();
    }

    public function productLinks()
    {
        return $this->hasMany(ProductPdfDesignProduct::class);
    }
}
