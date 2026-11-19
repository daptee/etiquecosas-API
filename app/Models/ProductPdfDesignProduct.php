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
        'page_id',
        'label_element_id',
        'combo_design_id',
        'sort_order',
    ];

    public function design()
    {
        return $this->belongsTo(ProductPdfDesign::class, 'product_pdf_design_id');
    }

    /**
     * El diseño "combo" (páginas de varios diseños) que armó este vínculo por
     * página — null = vínculo de siempre. Ver
     * database/sql/alter_product_pdf_design_products_add_combo_design.sql.
     */
    public function combo()
    {
        return $this->belongsTo(ProductPdfDesign::class, 'combo_design_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
