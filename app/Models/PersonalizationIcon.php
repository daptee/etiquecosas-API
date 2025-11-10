<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\GeneralStatus;

class PersonalizationIcon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'icon',
        'status_id',
    ];

    public function generalStatus() 
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }
}
