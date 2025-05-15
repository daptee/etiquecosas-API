<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GeneralStatus;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'categoryId',
        'name',
        'img',
        'icon',
        'color',
        'status',
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
        return $this->belongsTo(GeneralStatus::class, 'status');
    }
}
