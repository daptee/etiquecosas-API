<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanOldBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clean-old-backups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina backups de la base de datos con más de 2 semanas de antigüedad';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando limpieza de backups antiguos...');

        try {
            $backupPath = storage_path('app/backups');

            // Verificar que exista el directorio de backups
            if (!file_exists($backupPath)) {
                $this->warn('El directorio de backups no existe: ' . $backupPath);
                Log::warning('Clean Backups: El directorio de backups no existe');
                return Command::SUCCESS;
            }

            // Obtener todos los archivos .sql del directorio
            $files = glob($backupPath . '/*.sql');

            if (empty($files)) {
                $this->info('No hay archivos de backup para procesar.');
                Log::info('Clean Backups: No se encontraron archivos de backup');
                return Command::SUCCESS;
            }

            // Fecha límite: hace 2 semanas (14 días)
            $cutoffDate = Carbon::now()->subWeeks(2);

            $deletedCount = 0;
            $deletedSize = 0;
            $keptCount = 0;

            foreach ($files as $file) {
                // Obtener la fecha de modificación del archivo
                $fileModificationTime = filemtime($file);
                $fileDate = Carbon::createFromTimestamp($fileModificationTime);

                // Si el archivo tiene más de 2 semanas, eliminarlo
                if ($fileDate->lt($cutoffDate)) {
                    $filename = basename($file);
                    $filesize = filesize($file);
                    $filesizeMB = round($filesize / 1024 / 1024, 2);

                    if (unlink($file)) {
                        $this->info("✓ Eliminado: {$filename} ({$filesizeMB} MB) - Fecha: {$fileDate->format('Y-m-d H:i:s')}");
                        Log::info("Clean Backups: Archivo eliminado - {$filename} ({$filesizeMB} MB)");
                        $deletedCount++;
                        $deletedSize += $filesize;
                    } else {
                        $this->error("✗ Error al eliminar: {$filename}");
                        Log::error("Clean Backups: Error al eliminar - {$filename}");
                    }
                } else {
                    $keptCount++;
                }
            }

            // Resumen de ejecución
            $this->info("\n========== RESUMEN ==========");
            $this->info("Total de archivos procesados: " . count($files));
            $this->info("Archivos eliminados: {$deletedCount}");
            $this->info("Archivos conservados: {$keptCount}");

            if ($deletedSize > 0) {
                $deletedSizeMB = round($deletedSize / 1024 / 1024, 2);
                $this->info("Espacio liberado: {$deletedSizeMB} MB");
            }

            $this->info("=============================\n");

            Log::info("Clean Backups: Limpieza completada - Eliminados: {$deletedCount}, Conservados: {$keptCount}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error inesperado: {$e->getMessage()}");
            Log::error("Clean Backups: Error inesperado - {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
