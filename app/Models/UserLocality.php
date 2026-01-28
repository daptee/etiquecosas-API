<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLocality extends Model
{
    protected $table = 'user_localities';

    protected $fillable = [
        'user_id',
        'locality_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function locality()
    {
        return $this->belongsTo(Locality::class);
    }
}
