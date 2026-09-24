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
        // Si esta venta viene de un carrito recuperado, el abandoned_cart_log
        // está en la venta original (recovered_sale_id apunta hacia arriba en
        // la cadena — no confundir con sale_id, que es la asociación manual
        // de ventas y no tiene nada que ver con esto). Se sube por la cadena
        // hasta encontrarlo.
        $current = $sale;
        $depth = 0;

        while ($current && $depth < 10) {
            $log = static::where('sale_id', $current->id)->whereNull('converted_at')->first();

            if ($log) {
                $log->update([
                    'converted_at' => now(),
                    'converted_via' => $log->impact_2_sent_at ? 'impact_2' : 'impact_1',
                ]);
                return;
            }

            if (!$current->recovered_sale_id) {
                return;
            }

            $current = Sale::find($current->recovered_sale_id);
            $depth++;
        }
    }
}
