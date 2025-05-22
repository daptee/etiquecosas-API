<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GeneralStatus;
use App\Models\ConfigurationTag;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'img',
        'icon',
        'color',
        'status_id',
        'tag_id',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'categoryId');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'categoryId');
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
