<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\PersonalizationColor;
use App\Models\PersonalizationIcon;

class ProductCustomization extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'customization',
    ];

    protected $casts = [
        'customization' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // 🔹 Colors relacionados
    public function getColorsAttribute()
    {
        $ids = $this->customization['colors'] ?? [];
        return PersonalizationColor::whereIn('id', $ids)->get();
    }

    // 🔹 Icons relacionados
    public function getIconsAttribute()
    {
        $ids = $this->customization['icons'] ?? [];
        return PersonalizationIcon::whereIn('id', $ids)->get();
    }

    public function toArray()
    {
        $array = parent::toArray();

        $array['customization'] = [
            'is_details_active' => $this->customization['is_details_active'] ?? 0,
            'is_colors_active' => $this->customization['is_colors_active'] ?? 0,
            'is_icons_active' => $this->customization['is_icons_active'] ?? 0,
            'is_name_active' => $this->customization['is_name_active'] ?? 0,
            'is_last_name_active ' => $this->customization['is_last_name_active'] ?? 0,
            'is_text_active' => $this->customization['is_text_active'] ?? 0,
            'colors' => $this->colors, // 👈 trae objetos PersonalizationColor
            'icons' => $this->icons,  // 👈 trae objetos PersonalizationIcon
        ];

        return $array;
    }
}
