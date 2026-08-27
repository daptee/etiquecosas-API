<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartFirstReminderMail;
use App\Mail\AbandonedCartSecondReminderMail;
use App\Models\AbandonedCartLog;
use App\Models\Coupon;
use App\Models\CouponStatus;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessAbandonedCarts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'carts:process-abandoned
        {--wait-minutes=20 : Minutos sin actividad antes de considerar el carrito abandonado}
        {--impact2-days=3 : Días de espera tras el Impacto 1 antes de evaluar el Impacto 2}
        {--min-amount=100000 : Monto a partir del cual se envía el Impacto 2 con cupón}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detecta carritos abandonados y envía los recordatorios del flujo (Impacto 1 y 2)';

    private const COUPON_CODE = 'ETIQUECARRITO';
    private const COUPON_PERCENT = 15;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $waitMinutes = (int) $this->option('wait-minutes');
        $impact2Days = (int) $this->option('impact2-days');
        $minAmount = (float) $this->option('min-amount');

        $this->processNewAbandonments($waitMinutes, $minAmount);
        $this->processImpact2();

        return Command::SUCCESS;
    }

    private function processNewAbandonments(int $waitMinutes, float $minAmount): void
    {
        $sales = Sale::where('sale_status_id', 8)
            ->where('created_at', '<=', now()->subMinutes($waitMinutes))
            ->whereDoesntHave('abandonedCartLog')
            ->with('client', 'products.product.images')
            ->get();

        $this->info("Carritos candidatos a abandono: {$sales->count()}");

        foreach ($sales as $sale) {
            if (!$sale->client || !$sale->client->email) {
                Log::info('Carrito sin mail de cliente, fuera del flujo de abandono', ['sale_id' => $sale->id]);
                continue;
            }

            try {
                $log = AbandonedCartLog::create([
                    'sale_id' => $sale->id,
                    'client_email' => $sale->client->email,
                    'total' => $sale->total,
                    'abandoned_at' => now(),
                    'impact_2_eligible' => $sale->total > $minAmount,
                ]);

                Mail::to($sale->client->email)->send(new AbandonedCartFirstReminderMail($sale));

                $log->update(['impact_1_sent_at' => now()]);

                $this->info("Impacto 1 enviado - venta #{$sale->id} - {$sale->client->email}");
            } catch (\Throwable $e) {
                Log::error('Error procesando abandono de carrito (Impacto 1)', [
                    'sale_id' => $sale->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processImpact2(): void
    {
        $impact2Days = (int) $this->option('impact2-days');

        $logs = AbandonedCartLog::whereNull('impact_2_sent_at')
            ->whereNull('converted_at')
            ->where('impact_2_eligible', true)
            ->where('abandoned_at', '<=', now()->subDays($impact2Days))
            ->with('sale.client')
            ->get();

        $this->info("Carritos candidatos a Impacto 2: {$logs->count()}");

        $minAmount = (float) $this->option('min-amount');

        foreach ($logs as $log) {
            $sale = $log->sale;

            if (!$sale || $sale->sale_status_id != 8) {
                // Ya no está pendiente de pago; el hook de conversión debería haberlo marcado.
                continue;
            }

            try {
                $coupon = $this->getOrCreateCoupon($minAmount);

                Mail::to($sale->client->email)->send(new AbandonedCartSecondReminderMail($sale, $coupon));

                $log->update([
                    'coupon_id' => $coupon->id,
                    'impact_2_sent_at' => now(),
                ]);

                $this->info("Impacto 2 enviado - venta #{$sale->id} - {$sale->client->email}");
            } catch (\Throwable $e) {
                Log::error('Error procesando abandono de carrito (Impacto 2)', [
                    'sale_id' => $sale->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getOrCreateCoupon(float $minAmount): Coupon
    {
        return Coupon::firstOrCreate(
            ['code' => self::COUPON_CODE],
            [
                'name' => 'Recuperación de carrito abandonado',
                'date_from' => now(),
                'date_to' => now()->addYears(5),
                'min_amount' => $minAmount,
                'type' => 'Porcentaje',
                'value' => self::COUPON_PERCENT,
                'applies_to_all_products' => true,
                'applies_to_shipping' => true,
                'applies_to_web' => true,
                'applies_to_sale_price' => false,
                'max_use_per_user' => 1,
                'max_use_per_code' => 0,
                'coupon_status_id' => $this->getActiveCouponStatusId(),
            ]
        );
    }

    private function getActiveCouponStatusId(): int
    {
        $status = CouponStatus::where('name', 'like', '%activ%')->first();

        if (!$status) {
            $status = CouponStatus::create(['name' => 'Activo']);
        }

        return $status->id;
    }
}
