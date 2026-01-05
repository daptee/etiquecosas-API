<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralContent extends Model
{
    use HasFactory;

    protected $table = 'general_content';

    protected $fillable = [
        'content',
    ];

    protected $casts = [
        'content' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
