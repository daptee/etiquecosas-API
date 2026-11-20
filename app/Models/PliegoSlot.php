<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un lugar ocupado de un pliego: la página `page_number` del PDF
 * storage/app/pdf/planchas/{pdf_folder}/{pdf_file}. Ver PLIEGOS.md.
 */
class PliegoSlot extends Model
{
    protected $fillable = [
        'pliego_id',
        'position',
        'sale_id',
        'sale_product_id',
        'pdf_folder',
        'pdf_file',
        'page_number',
    ];

    protected $casts = [
        'position' => 'integer',
        'page_number' => 'integer',
    ];

    public function pliego()
    {
        return $this->belongsTo(Pliego::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function saleProduct()
    {
        return $this->belongsTo(SaleProduct::class, 'sale_product_id');
    }

    /** Clave única de la hoja, igual a la que devuelve el listado de hojas disponibles. */
    public function sheetKey(): string
    {
        return "{$this->pdf_folder}/{$this->pdf_file}#{$this->page_number}";
    }
}
