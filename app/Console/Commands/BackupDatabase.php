<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Realiza un backup completo de la base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando backup de la base de datos...');

        try {
            // Obtener configuración de la base de datos
            $dbHost = env('DB_HOST');
            $dbPort = env('DB_PORT', 3306);
            $dbName = env('DB_DATABASE');
            $dbUser = env('DB_USERNAME');
            $dbPassword = env('DB_PASSWORD');

            // Validar que existan las credenciales
            if (!$dbHost || !$dbName || !$dbUser) {
                $this->error('Error: Faltan credenciales de la base de datos en el archivo .env');
                Log::error('Backup DB: Faltan credenciales de base de datos');
                return Command::FAILURE;
            }

            // Crear directorio de backups si no existe
            $backupPath = storage_path('app/backups');
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
                $this->info('Directorio de backups creado: ' . $backupPath);
            }

            // Nombre del archivo de backup con fecha y hora
            $timestamp = Carbon::now()->format('Y-m-d_His');
            $filename = "backup_{$dbName}_{$timestamp}.sql";
            $filepath = $backupPath . '/' . $filename;

            // Construir el comando mysqldump
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s',
                $dbHost,
                $dbPort,
                $dbUser,
                $dbPassword,
                $dbName
            );

            // Ejecutar el comando usando Process de Laravel
            $this->info('Ejecutando mysqldump...');

            $result = Process::run($command);

            // Verificar el resultado
            if (!$result->successful()) {
                $errorMessage = $result->errorOutput() ?: $result->output();
                $this->error("Error al crear el backup: {$errorMessage}");
                Log::error("Backup DB: Error al ejecutar mysqldump - {$errorMessage}");
                return Command::FAILURE;
            }

            // Guardar el output en el archivo
            file_put_contents($filepath, $result->output());

            // Verificar que el archivo se creó correctamente
            if (!file_exists($filepath) || filesize($filepath) === 0) {
                $this->error("Error: El archivo de backup está vacío o no se pudo crear");
                Log::error("Backup DB: El archivo de backup está vacío o no se pudo crear");

                if (file_exists($filepath)) {
                    unlink($filepath);
                }

                return Command::FAILURE;
            }

            // Obtener tamaño del archivo
            $filesize = filesize($filepath);
            $filesizeMB = round($filesize / 1024 / 1024, 2);

            $this->info("✓ Backup creado exitosamente: {$filename}");
            $this->info("  Tamaño: {$filesizeMB} MB");
            $this->info("  Ubicación: {$filepath}");

            Log::info("Backup DB: Backup creado exitosamente - {$filename} ({$filesizeMB} MB)");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error inesperado: {$e->getMessage()}");
            Log::error("Backup DB: Error inesperado - {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
