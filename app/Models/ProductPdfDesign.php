<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPdfDesign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'label_shape_id',
        'theme_key',
        'name',
        'data',
        'is_published',
        'status_id',
    ];

    protected $casts = [
        'data' => 'array',
        'is_published' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function labelShape()
    {
        return $this->belongsTo(LabelShape::class);
    }

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }
}
