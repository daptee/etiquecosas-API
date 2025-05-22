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
        'status_id',
    ];

    public function status() 
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
