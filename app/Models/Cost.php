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
        'status_id',
    ];

    public function status()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    public function prices()
    {
        return $this->hasMany(CostPrice::class, 'cost_id');
    }
}
