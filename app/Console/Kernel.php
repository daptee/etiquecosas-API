<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        // Nota: este proyecto NO corre `php artisan schedule:run` vía crontab.
        // Los jobs periódicos se disparan por HTTP (ver BackupController y
        // las rutas /run, /clean, /notify-production, /process-abandoned-carts
        // en routes/api.php), gestionadas desde el panel de cron del hosting.
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
