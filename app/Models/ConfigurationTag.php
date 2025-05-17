<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GeneralStatus;
use App\Models\Category;

class ConfigurationTag extends Model
{
    use HasFactory;

     protected $fillable = [
        'name',
        'color',
        'statusId',
    ];

    public function status() 
    {
        return $this->belongsTo(GeneralStatus::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
