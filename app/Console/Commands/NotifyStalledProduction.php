<?php

namespace App\Console\Commands;

use App\Mail\StalledProductionAlertMail;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyStalledProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:notify-stalled {--days=11 : Cantidad de días hábiles en producción}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifica internamente a info@etiquecosas.com.ar cuando una venta lleva N días hábiles en producción';

    /**
     * Feriados nacionales de Argentina (actualizar anualmente).
     */
    private function getHolidays(int $year): array
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

    /**
     * Calcula la fecha que estaba hace N días hábiles.
     */
    private function calculateBusinessDaysAgo(int $businessDays): Carbon
    {
        $date = Carbon::now()->startOfDay();
        $daysSubtracted = 0;

        // Obtener feriados del año actual y anterior (por si cruzamos de año)
        $holidays = array_merge(
            $this->getHolidays($date->year),
            $this->getHolidays($date->year - 1)
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
     * Execute the console command.
     */
    public function handle()
    {
        $businessDays = (int) $this->option('days');

        $this->info("Verificando ventas con {$businessDays} días hábiles en producción...");
        Log::info("Cronjob NotifyStalledProduction: Iniciando verificación de {$businessDays} días hábiles");

        // Calcular la fecha objetivo (hace N días hábiles)
        $targetDate = $this->calculateBusinessDaysAgo($businessDays);

        $this->info("Fecha objetivo calculada: {$targetDate->format('Y-m-d')} ({$targetDate->format('l')})");

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
            $this->info("No hay ventas que cumplan los criterios ({$businessDays} días hábiles en producción).");
            Log::info("Cronjob NotifyStalledProduction: No se encontraron ventas estancadas.");
            return Command::SUCCESS;
        }

        $this->info("Se encontraron {$sales->count()} venta(s) con {$businessDays} días hábiles en producción.");

        // Agregar la fecha de ingreso a producción a cada venta para mostrar en el email
        $sales->each(function ($sale) {
            $productionHistory = $sale->statusHistory->first();
            $sale->production_entry_date = $productionHistory
                ? Carbon::parse($productionHistory->date)->format('d/m/Y H:i')
                : 'N/A';
        });

        // Listar las ventas encontradas
        $this->table(
            ['ID Venta', 'Cliente', 'Ingreso a Producción'],
            $sales->map(function ($sale) {
                return [
                    $sale->id,
                    ($sale->client->name ?? 'Sin nombre') . ' ' . ($sale->client->lastname ?? ''),
                    $sale->production_entry_date
                ];
            })
        );

        try {
            // Enviar un único correo con todas las ventas estancadas
            Mail::to('info@etiquecosas.com.ar')->send(new StalledProductionAlertMail($sales, $businessDays));

            $this->info("✓ Notificación enviada a info@etiquecosas.com.ar");
            Log::info("Cronjob NotifyStalledProduction: Email enviado exitosamente con {$sales->count()} venta(s)");

        } catch (\Exception $e) {
            $this->error("✗ Error al enviar correo: {$e->getMessage()}");
            Log::error("Cronjob NotifyStalledProduction: Error al enviar correo - {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Resumen de ejecución
        $this->info("\n========== RESUMEN ==========");
        $this->info("Total de ventas notificadas: {$sales->count()}");
        $this->info("Destinatario: info@etiquecosas.com.ar");
        $this->info("=============================\n");

        return Command::SUCCESS;
    }
}
