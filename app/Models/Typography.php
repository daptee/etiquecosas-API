<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Typography extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'status_id',
    ];

    public function generalStatus()
    {
        return $this->belongsTo(GeneralStatus::class, 'status_id');
    }

    public function files()
    {
        return $this->hasMany(TypographyFile::class);
    }

    public function attributeValues()
    {
        return $this->hasMany(AttributeValue::class);
    }
}
