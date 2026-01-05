<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerService extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customer_services';

    protected $fillable = [
        'name',
        'status_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Status constants (based on general_statuses table)
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    // Relationship with general_statuses
    public function status()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    // Relationship with steps
    public function steps()
    {
        return $this->hasMany(CustomerServiceStep::class, 'customer_service_id')->orderBy('step_number', 'asc');
    }

    // Scope to get only active records
    public function scopeActive($query)
    {
        return $query->where('status_id', self::STATUS_ACTIVE);
    }
}
