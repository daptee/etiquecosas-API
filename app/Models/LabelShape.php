<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabelShape extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'shape_type',
        'width_cm',
        'height_cm',
        'is_system',
        'data',
        'status_id',
    ];

    protected $casts = [
        'data' => 'array',
        'is_system' => 'boolean',
    ];

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    public function productPdfDesigns()
    {
        return $this->hasMany(ProductPdfDesign::class);
    }
}
