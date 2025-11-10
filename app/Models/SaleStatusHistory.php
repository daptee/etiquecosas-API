<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleStatusHistory extends Model
{
    protected $table = 'sales_status_history';

    protected $fillable = [
        'sale_id',
        'sale_status_id',
        'date',
    ];

    public $timestamps = false;

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function status()
    {
        return $this->belongsTo(SaleStatus::class, 'sale_status_id');
    }
}
