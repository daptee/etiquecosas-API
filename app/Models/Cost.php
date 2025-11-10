<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\GeneralStatus;
use App\Models\CostPrice;

class Cost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'status_id',
    ];

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    public function prices()
    {
        return $this->hasMany(CostPrice::class, 'cost_id');
    }
}
