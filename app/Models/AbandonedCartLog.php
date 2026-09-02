<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AbandonedCartLog extends Model
{
    protected $fillable = [
        'sale_id',
        'uid',
        'client_email',
        'total',
        'abandoned_at',
        'impact_1_sent_at',
        'impact_2_eligible',
        'impact_2_sent_at',
        'coupon_id',
        'converted_at',
        'converted_via',
    ];

    protected $casts = [
        'abandoned_at' => 'datetime',
        'impact_1_sent_at' => 'datetime',
        'impact_2_sent_at' => 'datetime',
        'converted_at' => 'datetime',
        'impact_2_eligible' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AbandonedCartLog $log) {
            if (!$log->uid) {
                $log->uid = (string) Str::uuid();
            }
        });
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public static function markConvertedForSale(Sale $sale): void
    {
        $log = static::where('sale_id', $sale->id)->whereNull('converted_at')->first();

        if (!$log) {
            return;
        }

        $log->update([
            'converted_at' => now(),
            'converted_via' => $log->impact_2_sent_at ? 'impact_2' : 'impact_1',
        ]);
    }
}
