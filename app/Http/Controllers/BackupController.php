<?php

namespace App\Http\Controllers;

use App\Mail\OrderProductionReminderMail;
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
     * Notificar pedidos en producción (5 días)
     */
    public function notifyProductionOrders()
    {
        try {
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
                $this->logAudit(null, 'Notify Production Orders', [], ['status' => 'success', 'message' => 'No hay pedidos que cumplan los criterios']);
                return $this->success(['output' => 'No hay pedidos que cumplan los criterios (5 días en producción).'], 'Verificación completada');
            }

            $successCount = 0;
            $failureCount = 0;
            $results = [];

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

            $summary = "Total: {$sales->count()}, Exitosos: {$successCount}, Fallidos: {$failureCount}";
            Log::info("Notify Production finalizado - {$summary}");

            $this->logAudit(null, 'Notify Production Orders', [], [
                'status' => 'success',
                'total' => $sales->count(),
                'success' => $successCount,
                'failed' => $failureCount
            ]);

            return $this->success([
                'output' => implode("\n", $results),
                'summary' => $summary
            ], 'Notificaciones de pedidos en producción procesadas');

        } catch (\Exception $e) {
            $this->logAudit(null, 'Notify Production Orders Error', [], $e->getMessage());
            return $this->error('Error al notificar pedidos: ' . $e->getMessage(), 500);
        }
    }
}
