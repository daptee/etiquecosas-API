<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypographyFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'typography_id',
        'file_path',
        'file_name',
        'file_type',
    ];

    public function typography()
    {
        return $this->belongsTo(Typography::class);
    }
}
