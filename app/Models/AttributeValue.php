<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GeneralStatus;
use App\Models\AttributeValue; 

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'statusId',
    ];

    public function status() 
    {
        return $this->belongsTo(GeneralStatus::class);
    }

    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

}
