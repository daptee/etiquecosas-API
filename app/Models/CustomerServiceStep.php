<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerServiceStep extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customer_service_steps';

    protected $fillable = [
        'customer_service_id',
        'step_number',
        'title',
        'description',
        'icon',
        'image_1',
        'image_2',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationship with customer service
    public function customerService()
    {
        return $this->belongsTo(CustomerService::class, 'customer_service_id');
    }
}
