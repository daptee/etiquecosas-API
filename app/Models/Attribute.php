<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GeneralStatus;
use App\Models\AttributeValue;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status_id',
    ];

    public function generalStatus() 
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    public function values() 
    {
        return $this->hasMany(AttributeValue::class);
    }

}
