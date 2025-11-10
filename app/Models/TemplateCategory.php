<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ShippingTemplate;
use App\Models\GeneralStatus;

class TemplateCategory extends Model
{
    use HasFactory;

    protected $table = 'templates_categories';

    protected $fillable = [
        'name',
        'status_id',
    ];

    public function shippingTemplates()
    {
        return $this->hasMany(ShippingTemplate::class, 'id_shipping_template_category');
    }

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }
}
