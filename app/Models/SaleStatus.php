<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleStatus extends Model
{
    protected $table = 'sale_status';
    protected $fillable = ['name'];
}