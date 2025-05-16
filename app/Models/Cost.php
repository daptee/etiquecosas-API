<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GeneralStatus;
use App\Models\CostPrice;

class Cost extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'statusId',
    ];

    public function status()
    {
        return $this->belongsTo(GeneralStatus::class);
    }

    public function prices()
    {
        return $this->hasMany(CostPrice::class, 'costId');
    }
}
