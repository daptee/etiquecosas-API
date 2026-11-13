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
        'user_id',
        'name',
        'data',
        'is_published',
        'status_id',
        'fill_mode',
        'accumulation_group_attribute',
        'accumulation_split_attributes',
        'accumulation_cols',
        'accumulation_rows',
        'accumulation_gap_cm',
        'accumulation_quantity_mode',
        'pages_output_mode',
    ];

    protected $casts = [
        'data' => 'array',
        'is_published' => 'boolean',
        'accumulation_cols' => 'integer',
        'accumulation_rows' => 'integer',
        'accumulation_gap_cm' => 'float',
        'accumulation_split_attributes' => 'array',
    ];

    /** Diseño que se completa solo, página a página, a medida que entran pedidos del día (ver AccumulatingPdfDesignService) en vez de generar un PDF nuevo por pedido. */
    public function esAcumulable(): bool
    {
        return ($this->fill_mode ?? 'single') === 'accumulating';
    }

    /** Un diseño con 2+ páginas: ¿cada una en su propio PDF (sufijo "-1", "-2"...) en vez de un único PDF combinado? Default sí — ver EtiquetaService::generarEtiquetasDesdeDesign(). */
    public function paginasPorSeparado(): bool
    {
        return ($this->pages_output_mode ?? 'separate') !== 'combined';
    }

    /** Diseño acumulable con un pedido de cantidad > 1: ¿se repite la celda esa cantidad de veces (true), o se dibuja una sola celda + un cartelito "xN" (false)? Default repetir — ver AccumulatingPdfDesignService. */
    public function duplicaCeldaPorCantidad(): bool
    {
        return ($this->accumulation_quantity_mode ?? 'duplicate') !== 'badge';
    }

    public function labelShape()
    {
        return $this->belongsTo(LabelShape::class);
    }

    /** Usuario que creó el diseño (null en los diseños anteriores a la columna). */
    public function user()
    {
        return $this->belongsTo(User::class);
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
            ->withPivot('id', 'theme_key', 'page_id', 'label_element_id', 'sort_order')
            ->withTimestamps();
    }

    public function productLinks()
    {
        return $this->hasMany(ProductPdfDesignProduct::class);
    }
}
