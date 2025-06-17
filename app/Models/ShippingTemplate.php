<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TemplateCategory;
use App\Models\GeneralStatus;

class ShippingTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_shipping_template_category',
        'description',
        'status_id',
    ];

    public function templateCategory()
    {
        return $this->belongsTo(TemplatesCategory::class, 'id_shipping_template_category');
    }
}
