<?php

namespace App\Console\Commands;

use App\Mail\OrderProductionReminderMail;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyProductionOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:notify-production';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía notificaciones a clientes con pedidos que llevan 5 días en producción';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verificando pedidos en producción...');

        // Fecha de hace exactamente 5 días (solo la fecha, sin considerar hora)
        $targetDate = Carbon::now()->subDays(5)->startOfDay();

        // Buscar pedidos que:
        // 1. Estén en estado "En producción" (sale_status_id = 2)
        // 2. Que ingresaron a producción hace 5 días
        $sales = Sale::where('sale_status_id', 2)
            ->whereHas('statusHistory', function ($query) use ($targetDate) {
                $query->where('sale_status_id', 2)
                    ->whereDate('date', '=', $targetDate->toDateString());
            })
            ->with(['client', 'products', 'status'])
            ->get();

        if ($sales->isEmpty()) {
            $this->info('No hay pedidos que cumplan los criterios (5 días en producción).');
            Log::info('Cronjob: No se encontraron pedidos con 5 días en producción.');
            return Command::SUCCESS;
        }

        $this->info("Se encontraron {$sales->count()} pedido(s) con 5 días en producción.");

        $successCount = 0;
        $failureCount = 0;

        foreach ($sales as $sale) {
            try {
                // Verificar que el cliente tenga email válido
                if (!$sale->client || !$sale->client->email) {
                    $this->warn("Pedido #{$sale->id}: Cliente sin email válido. Saltando...");
                    Log::warning("Cronjob: Pedido #{$sale->id} - Cliente sin email válido");
                    $failureCount++;
                    continue;
                }

                // Enviar el correo usando la plantilla de recordatorio
                Mail::to($sale->client->email)->send(new OrderProductionReminderMail($sale));

                $this->info("✓ Notificación enviada a {$sale->client->email} - Pedido #{$sale->id}");
                Log::info("Cronjob: Correo enviado exitosamente - Pedido #{$sale->id} - Cliente: {$sale->client->email}");

                $successCount++;
            } catch (\Exception $e) {
                $this->error("✗ Error al enviar correo para pedido #{$sale->id}: {$e->getMessage()}");
                Log::error("Cronjob: Error al enviar correo - Pedido #{$sale->id} - Error: {$e->getMessage()}");
                $failureCount++;
            }
        }

        // Resumen de ejecución
        $this->info("\n========== RESUMEN ==========");
        $this->info("Total de pedidos procesados: {$sales->count()}");
        $this->info("Correos enviados exitosamente: {$successCount}");

        if ($failureCount > 0) {
            $this->warn("Correos fallidos: {$failureCount}");
        }

        $this->info("=============================\n");

        Log::info("Cronjob finalizado - Exitosos: {$successCount}, Fallidos: {$failureCount}");

        return Command::SUCCESS;
    }
}
