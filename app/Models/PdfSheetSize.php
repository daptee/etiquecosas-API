<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdfSheetSize extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'width_cm',
        'height_cm',
        'type_id',
        'default_columns',
        'default_rows',
        'is_system',
        'status_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'default_columns' => 'integer',
        'default_rows' => 'integer',
    ];

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    public function type()
    {
        return $this->belongsTo(PdfSheetSizeType::class, 'type_id');
    }
}
