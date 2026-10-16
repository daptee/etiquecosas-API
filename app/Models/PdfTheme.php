<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdfTheme extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'colors',
        'status_id',
    ];

    protected $casts = [
        'colors' => 'array',
    ];

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    public function icons()
    {
        return $this->belongsToMany(PersonalizationIcon::class, 'pdf_theme_icons')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('pdf_theme_icons.sort_order');
    }
}
