<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Cost;

class CostPrice extends Model
{
    use HasFactory;

     protected $fillable = [
        'cost_id',
        'price',
    ];

    public function cost()
    {
        return $this->belongsTo(Cost::class, 'cost_id');
    }
}
