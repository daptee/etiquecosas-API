<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Artisan;
use App\Traits\Auditable;

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
}
