<?php

namespace App\Http\Controllers;

use App\Services\PdfDirectoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ZipArchive;

class PdfDirectoryController extends Controller
{
    use ApiResponse;

    /**
     * GET ALL - Retorna el listado de carpetas ordenadas desde la más reciente a la más vieja
     * con paginado y filtros por rango de fechas y usuario
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $profileId = $user->profile_id;

            // Parámetros de paginación
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 15);

            // Filtros
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $filterUserId = $request->input('user_id'); // Solo para admin

            // Si es admin (profile_id 1) y se especifica user_id, filtrar por ese usuario
            $userId = null;
            if ($profileId == 1 && $filterUserId) {
                // Admin filtrando por usuario específico
                $userId = $filterUserId;
                $profileIdFiltro = 2; // Filtrar como diseñador
            } elseif ($profileId == 2) {
                // Diseñador: solo sus carpetas
                $userId = $user->id;
                $profileIdFiltro = 2;
            } else {
                // Admin sin filtro: ver todo
                $profileIdFiltro = null;
            }

            $resultado = PdfDirectoryService::getCarpetasPdf(
                $userId,
                $profileIdFiltro,
                $startDate,
                $endDate,
                $page,
                $perPage
            );

            return $this->success(
                $resultado,
                'Carpetas de PDF obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Error al obtener las carpetas de PDF: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * GET ALL PDF - Obtiene todos los PDFs de una fecha específica
     * Incluye PDFs de pedidos y de cintas (coser/planchar x24/x48)
     */
    public function getPdfsByDate($fecha)
    {
        try {
            $user = Auth::user();
            $profileId = $user->profile_id;

            // Validar formato de fecha
            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $fecha)) {
                return $this->error(
                    'Formato de fecha inválido. Use dd-mm-yyyy',
                    400
                );
            }

            $userId = null;
            if ($profileId == 2) {
                // Si es diseñador, solo ver sus PDFs
                $userId = $user->id;
            }

            $resultado = PdfDirectoryService::getPdfsDeUnaFecha($fecha, $userId, $profileId);

            return $this->success(
                $resultado,
                'PDFs obtenidos exitosamente'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Error al obtener los PDFs: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Descarga una carpeta completa en formato ZIP
     * Incluye PDFs de pedidos y de cintas
     */
    public function downloadCarpetaZip($fecha)
    {
        try {
            $user = Auth::user();
            $profileId = $user->profile_id;

            // Validar formato de fecha
            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $fecha)) {
                return $this->error(
                    'Formato de fecha inválido. Use dd-mm-yyyy',
                    400
                );
            }

            $userId = null;
            if ($profileId == 2) {
                $userId = $user->id;
            }

            // Obtener todos los PDFs de la fecha
            $pdfsData = PdfDirectoryService::getPdfsDeUnaFecha($fecha, $userId, $profileId);

            // Crear archivo ZIP temporal
            $zipFileName = "pdfs_{$fecha}.zip";
            $zipPath = storage_path("app/temp/{$zipFileName}");

            // Asegurar que existe la carpeta temporal
            if (!is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return $this->error(
                    'No se pudo crear el archivo ZIP',
                    500
                );
            }

            $archivosAgregados = 0;

            // Agregar PDFs de pedidos
            if (!empty($pdfsData['pdfs_pedidos'])) {
                foreach ($pdfsData['pdfs_pedidos'] as $pdf) {
                    if (file_exists($pdf['ruta'])) {
                        $zip->addFile($pdf['ruta'], 'pedidos/' . $pdf['nombre']);
                        $archivosAgregados++;
                    }
                }
            }

            // Agregar cintas coser
            if (!empty($pdfsData['cintas_coser']['x24']) && file_exists($pdfsData['cintas_coser']['x24']['ruta'])) {
                $zip->addFile(
                    $pdfsData['cintas_coser']['x24']['ruta'],
                    'cintas_coser/' . $pdfsData['cintas_coser']['x24']['nombre']
                );
                $archivosAgregados++;
            }

            if (!empty($pdfsData['cintas_coser']['x48']) && file_exists($pdfsData['cintas_coser']['x48']['ruta'])) {
                $zip->addFile(
                    $pdfsData['cintas_coser']['x48']['ruta'],
                    'cintas_coser/' . $pdfsData['cintas_coser']['x48']['nombre']
                );
                $archivosAgregados++;
            }

            // Agregar cintas planchar
            if (!empty($pdfsData['cintas_planchar']['x24']) && file_exists($pdfsData['cintas_planchar']['x24']['ruta'])) {
                $zip->addFile(
                    $pdfsData['cintas_planchar']['x24']['ruta'],
                    'cintas_planchar/' . $pdfsData['cintas_planchar']['x24']['nombre']
                );
                $archivosAgregados++;
            }

            if (!empty($pdfsData['cintas_planchar']['x48']) && file_exists($pdfsData['cintas_planchar']['x48']['ruta'])) {
                $zip->addFile(
                    $pdfsData['cintas_planchar']['x48']['ruta'],
                    'cintas_planchar/' . $pdfsData['cintas_planchar']['x48']['nombre']
                );
                $archivosAgregados++;
            }

            $zip->close();

            if ($archivosAgregados === 0) {
                // Eliminar ZIP vacío
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }

                return $this->error(
                    'No hay archivos PDF disponibles para esta fecha',
                    404
                );
            }

            // Descargar y luego eliminar el archivo temporal
            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return $this->error(
                'Error al generar el ZIP: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Descarga un PDF específico
     */
    public function downloadPdf($fecha, $nombrePdf)
    {
        try {
            $user = Auth::user();
            $profileId = $user->profile_id;

            // Validar formato de fecha
            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $fecha)) {
                return $this->error(
                    'Formato de fecha inválido. Use dd-mm-yyyy',
                    400
                );
            }

            // Obtener ruta del PDF
            $rutaPdf = PdfDirectoryService::getRutaPdf($fecha, $nombrePdf);

            if (!$rutaPdf || !file_exists($rutaPdf)) {
                return $this->error(
                    'PDF no encontrado',
                    404
                );
            }

            // Si es diseñador, verificar que el PDF pertenece a un pedido asignado
            if ($profileId == 2) {
                // Extraer ID de venta del nombre del archivo
                preg_match('/^(\d+)-/', $nombrePdf, $matches);

                if (!empty($matches[1])) {
                    $ventaId = $matches[1];

                    // Verificar que la venta está asignada al usuario
                    $ventaAsignada = \DB::table('sales')
                        ->where('id', $ventaId)
                        ->where('user_id', $user->id)
                        ->exists();

                    if (!$ventaAsignada) {
                        return $this->error(
                            'No tiene permisos para descargar este PDF',
                            403
                        );
                    }
                }
            }

            return response()->download($rutaPdf);
        } catch (\Exception $e) {
            return $this->error(
                'Error al descargar el PDF: ' . $e->getMessage(),
                500
            );
        }
    }
}
