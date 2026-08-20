<?php

namespace App\Http\Controllers;

use App\Mail\OrderProductionReminderMail;
use App\Mail\OrderProductionSellerAlertMail;
use App\Mail\StalledProductionAlertMail;
use App\Models\Sale;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BackupController extends Controller
{
    use ApiResponse, Auditable;

    /**
     * Ejecutar backup de la base de datos
     */
    public function createBackup()
    {
        try {
            $exitCode = Artisan::call('db:backup');
            $output = Artisan::output();

            if ($exitCode === 0) {
                $this->logAudit(null, 'Database Backup', [], ['status' => 'success', 'output' => $output]);
                return $this->success(['output' => $output], 'Backup de base de datos creado correctamente');
            }

            $this->logAudit(null, 'Database Backup Failed', [], ['status' => 'error', 'output' => $output]);
            return $this->error('Error al crear el backup: ' . $output, 500);

        } catch (\Exception $e) {
            $this->logAudit(null, 'Database Backup Error', [], $e->getMessage());
            return $this->error('Error al ejecutar el backup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Limpiar backups antiguos (más de 2 semanas)
     */
    public function cleanOldBackups()
    {

        try {
            $exitCode = Artisan::call('db:clean-old-backups');
            $output = Artisan::output();

            if ($exitCode === 0) {
                $this->logAudit(null, 'Clean Old Backups', [], ['status' => 'success', 'output' => $output]);
                return $this->success(['output' => $output], 'Limpieza de backups antiguos completada');
            }

            $this->logAudit(null, 'Clean Old Backups Failed', [], ['status' => 'error', 'output' => $output]);
            return $this->error('Error al limpiar backups: ' . $output, 500);

        } catch (\Exception $e) {
            $this->logAudit(null, 'Clean Old Backups Error', [], $e->getMessage());
            return $this->error('Error al limpiar backups: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Notificar pedidos en producción (5 días al cliente, 10 días internamente)
     */
    public function notifyProductionOrders()
    {
        try {
            $results = [];

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
                Log::info('Notify Production: No se encontraron pedidos con 5 días en producción.');
                $results[] = 'No hay pedidos que cumplan los criterios (5 días en producción).';
                $successCount = 0;
                $failureCount = 0;
            } else {
                $successCount = 0;
                $failureCount = 0;

                foreach ($sales as $sale) {
                    try {
                        // Verificar que el cliente tenga email válido
                        if (!$sale->client || !$sale->client->email) {
                            Log::warning("Notify Production: Pedido #{$sale->id} - Cliente sin email válido");
                            $failureCount++;
                            $results[] = "Pedido #{$sale->id}: Cliente sin email válido";
                            continue;
                        }

                        // Enviar el correo usando la plantilla de recordatorio
                        Mail::to($sale->client->email)->send(new OrderProductionReminderMail($sale));

                        Log::info("Notify Production: Correo enviado exitosamente - Pedido #{$sale->id} - Cliente: {$sale->client->email}");
                        $successCount++;
                        $results[] = "Pedido #{$sale->id}: Notificación enviada a {$sale->client->email}";

                    } catch (\Exception $e) {
                        Log::error("Notify Production: Error al enviar correo - Pedido #{$sale->id} - Error: {$e->getMessage()}");
                        $failureCount++;
                        $results[] = "Pedido #{$sale->id}: Error - {$e->getMessage()}";
                    }
                }
            }

            $summary = "Total: {$sales->count()}, Exitosos: {$successCount}, Fallidos: {$failureCount}";
            Log::info("Notify Production finalizado - {$summary}");

            $this->logAudit(null, 'Notify Production Orders', [], [
                'status' => 'success',
                'total' => $sales->count(),
                'success' => $successCount,
                'failed' => $failureCount
            ]);

            // Además, avisar internamente a MAIL_NOTIFICATION_TO los pedidos que
            // cumplen exactamente 10 días en producción.
            $stalledResult = $this->notifyStalledProductionToInternalMail();
            $results[] = $stalledResult;

            return $this->success([
                'output' => implode("\n", $results),
                'summary' => $summary
            ], 'Notificaciones de pedidos en producción procesadas');

        } catch (\Exception $e) {
            $this->logAudit(null, 'Notify Production Orders Error', [], $e->getMessage());
            return $this->error('Error al notificar pedidos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Avisa internamente a MAIL_NOTIFICATION_TO los pedidos que cumplen
     * exactamente 10 días (calendario) en producción.
     */
    private function notifyStalledProductionToInternalMail(): string
    {
        $days = 10;
        $targetDate = Carbon::now()->subDays($days)->startOfDay();

        $stalledSales = Sale::where('sale_status_id', 2)
            ->whereHas('statusHistory', function ($query) use ($targetDate) {
                $query->where('sale_status_id', 2)
                    ->whereDate('date', '=', $targetDate->toDateString());
            })
            ->with(['client', 'products', 'status', 'statusHistory' => function ($query) {
                $query->where('sale_status_id', 2)->orderBy('date', 'asc');
            }])
            ->get();

        if ($stalledSales->isEmpty()) {
            Log::info("Notify Production: No hay pedidos con {$days} días en producción.");
            return "No hay pedidos con {$days} días en producción.";
        }

        $notifyEmail = env('MAIL_NOTIFICATION_TO');

        if (!$notifyEmail) {
            Log::warning('Notify Production: MAIL_NOTIFICATION_TO no configurado en .env');
            return "Se encontraron {$stalledSales->count()} pedido(s) con {$days} días en producción, pero MAIL_NOTIFICATION_TO no está configurado.";
        }

        $stalledSales->each(function ($sale) {
            $productionHistory = $sale->statusHistory->first();
            $sale->production_entry_date = $productionHistory
                ? Carbon::parse($productionHistory->date)->format('d/m/Y H:i')
                : 'N/A';
        });

        try {
            Mail::to($notifyEmail)->send(new OrderProductionSellerAlertMail($stalledSales, $days));

            $salesIds = $stalledSales->pluck('id')->toArray();
            Log::info("Notify Production: Alerta de {$days} días enviada a {$notifyEmail} - Pedidos: " . implode(', ', $salesIds));

            $this->logAudit(null, 'Notify Production Orders - Internal Alert', [], [
                'status' => 'success',
                'days' => $days,
                'notify_email' => $notifyEmail,
                'sales_ids' => $salesIds,
            ]);

            return "Alerta interna enviada a {$notifyEmail} - Pedido(s) con {$days} días en producción: " . implode(', ', $salesIds);
        } catch (\Exception $e) {
            Log::error("Notify Production: Error al enviar alerta de {$days} días - {$e->getMessage()}");
            return "Error al enviar alerta interna de {$days} días: {$e->getMessage()}";
        }
    }

    /**
     * Notificar ventas estancadas en producción (11 días hábiles)
     * Envía alerta interna a info@etiquecosas.com.ar
     */
    public function notifyStalledProduction()
    {
        try {
            $businessDays = 11;

            // Calcular la fecha objetivo (hace N días hábiles)
            $targetDate = $this->calculateBusinessDaysAgo($businessDays);

            Log::info("Notify Stalled Production: Fecha objetivo calculada: {$targetDate->format('Y-m-d')}");

            // Buscar ventas que:
            // 1. Estén en estado "En producción" (sale_status_id = 2)
            // 2. Que ingresaron a producción hace exactamente N días hábiles
            $sales = Sale::where('sale_status_id', 2)
                ->whereHas('statusHistory', function ($query) use ($targetDate) {
                    $query->where('sale_status_id', 2)
                        ->whereDate('date', '=', $targetDate->toDateString());
                })
                ->with(['client', 'products', 'status', 'statusHistory' => function ($query) {
                    $query->where('sale_status_id', 2)->orderBy('date', 'asc');
                }])
                ->get();

            if ($sales->isEmpty()) {
                Log::info("Notify Stalled Production: No se encontraron ventas con {$businessDays} días hábiles en producción.");
                $this->logAudit(null, 'Notify Stalled Production', [], [
                    'status' => 'success',
                    'message' => "No hay ventas con {$businessDays} días hábiles en producción"
                ]);
                return $this->success([
                    'output' => "No hay ventas que cumplan los criterios ({$businessDays} días hábiles en producción).",
                    'target_date' => $targetDate->format('Y-m-d')
                ], 'Verificación completada');
            }

            // Agregar la fecha de ingreso a producción a cada venta
            $sales->each(function ($sale) {
                $productionHistory = $sale->statusHistory->first();
                $sale->production_entry_date = $productionHistory
                    ? Carbon::parse($productionHistory->date)->format('d/m/Y H:i')
                    : 'N/A';
            });

            // Enviar un único correo con todas las ventas estancadas
            Mail::to('info@etiquecosas.com.ar')->send(new StalledProductionAlertMail($sales, $businessDays));

            $salesIds = $sales->pluck('id')->toArray();
            Log::info("Notify Stalled Production: Email enviado exitosamente con {$sales->count()} venta(s): " . implode(', ', $salesIds));

            $this->logAudit(null, 'Notify Stalled Production', [], [
                'status' => 'success',
                'total' => $sales->count(),
                'sales_ids' => $salesIds,
                'business_days' => $businessDays
            ]);

            return $this->success([
                'output' => "Notificación enviada a info@etiquecosas.com.ar",
                'sales_count' => $sales->count(),
                'sales_ids' => $salesIds,
                'target_date' => $targetDate->format('Y-m-d'),
                'business_days' => $businessDays
            ], "Alerta de ventas estancadas enviada ({$sales->count()} ventas)");

        } catch (\Exception $e) {
            Log::error("Notify Stalled Production Error: {$e->getMessage()}");
            $this->logAudit(null, 'Notify Stalled Production Error', [], $e->getMessage());
            return $this->error('Error al notificar ventas estancadas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Calcula la fecha que estaba hace N días hábiles (excluyendo fines de semana y feriados).
     */
    private function calculateBusinessDaysAgo(int $businessDays): Carbon
    {
        $date = Carbon::now()->startOfDay();
        $daysSubtracted = 0;

        // Obtener feriados del año actual y anterior
        $holidays = array_merge(
            $this->getArgentinaHolidays($date->year),
            $this->getArgentinaHolidays($date->year - 1)
        );

        while ($daysSubtracted < $businessDays) {
            $date->subDay();

            $isWeekend = $date->isWeekend();
            $isHoliday = in_array($date->format('Y-m-d'), $holidays);

            if (!$isWeekend && !$isHoliday) {
                $daysSubtracted++;
            }
        }

        return $date;
    }

    /**
     * Feriados nacionales de Argentina (actualizar anualmente).
     */
    private function getArgentinaHolidays(int $year): array
    {
        return [
            "$year-01-01", // Año Nuevo
            "$year-02-12", // Carnaval
            "$year-02-13", // Carnaval
            "$year-03-24", // Día de la Memoria
            "$year-04-02", // Día del Veterano y de los Caídos en Malvinas
            "$year-05-01", // Día del Trabajador
            "$year-05-25", // Día de la Revolución de Mayo
            "$year-06-17", // Paso a la Inmortalidad del Gral. Güemes
            "$year-06-20", // Día de la Bandera
            "$year-07-09", // Día de la Independencia
            "$year-08-17", // Paso a la Inmortalidad del Gral. San Martín
            "$year-10-12", // Día del Respeto a la Diversidad Cultural
            "$year-11-20", // Día de la Soberanía Nacional
            "$year-12-08", // Inmaculada Concepción de María
            "$year-12-25", // Navidad
        ];
    }
}
