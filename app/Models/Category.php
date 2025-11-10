<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\GeneralStatus;
use App\Models\ConfigurationTag;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'img',
        'icon',
        'color',
        'meta_data',
        'description',
        'banner',
        'status_id',
        'tag_id',
        'is_outstanding',
        'is_wholesale'
    ];

    protected $casts = [
        'meta_data' => 'array',
    ];     

    public function parent()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'category_id');
    }

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class, 'statusId');
    }

    public function tag()
    {
        return $this->belongsTo(ConfigurationTag::class, 'tag_id');
    }
}
