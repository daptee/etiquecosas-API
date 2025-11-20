<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Realiza un backup completo de la base de datos';

    public function handle()
    {
        $this->info('Iniciando backup de la base de datos...');

        try {
            $dbHost = env('DB_HOST');
            $dbPort = env('DB_PORT', 3306);
            $dbName = env('DB_DATABASE');
            $dbUser = env('DB_USERNAME');
            $dbPassword = env('DB_PASSWORD');
            $mysqldumpPath = env('MYSQLDUMP_PATH', 'mysqldump');

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

            // Construir el comando mysqldump con redirección directa al archivo
            $errorFile = $backupPath . '/error_' . $timestamp . '.txt';
            $command = sprintf(
                '"%s" --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s > "%s" 2>"%s"',
                $mysqldumpPath,
                $dbHost,
                $dbPort,
                $dbUser,
                $dbPassword,
                $dbName,
                $filepath,
                $errorFile
            );

            $this->info('Ejecutando mysqldump...');

            // Ejecutar el comando
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            // Leer errores si existen
            $errorMessage = '';
            if (file_exists($errorFile)) {
                $errorContent = file_get_contents($errorFile);
                // Limpiar caracteres no UTF-8 y warnings de password
                $errorMessage = mb_convert_encoding($errorContent, 'UTF-8', 'UTF-8');
                $errorMessage = preg_replace('/\[Warning\].*password.*\n?/i', '', $errorMessage);
                $errorMessage = trim($errorMessage);
                unlink($errorFile);
            }

            // Verificar el resultado - ignorar si solo hay warnings menores
            if ($returnVar !== 0) {
                $this->error("Error al crear el backup: {$errorMessage}");
                Log::error("Backup DB: Error al ejecutar mysqldump - {$errorMessage}");
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                return Command::FAILURE;
            }

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
