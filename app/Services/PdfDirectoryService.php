<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class PdfDirectoryService
{
    /**
     * Obtiene todas las carpetas de PDFs organizadas por fecha
     * Filtra por usuario diseñador si es necesario
     */
    public static function getCarpetasPdf($userId = null, $profileId = null, $startDate = null, $endDate = null, $page = 1, $perPage = 15)
    {
        $basePath = storage_path('app/pdf/planchas');

        if (!is_dir($basePath)) {
            return [
                'data' => [],
                'total' => 0,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => 1
            ];
        }

        // Obtener todas las carpetas de fechas
        $carpetas = File::directories($basePath);

        $carpetasConInfo = [];

        foreach ($carpetas as $carpeta) {
            $nombreCarpeta = basename($carpeta);

            // Validar formato de fecha d-m-Y
            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $nombreCarpeta)) {
                continue;
            }

            try {
                $fecha = Carbon::createFromFormat('d-m-Y', $nombreCarpeta);
            } catch (\Exception $e) {
                continue;
            }

            // Filtrar por rango de fechas si se especifica
            if ($startDate && $fecha->lt(Carbon::parse($startDate))) {
                continue;
            }
            if ($endDate && $fecha->gt(Carbon::parse($endDate))) {
                continue;
            }

            // Obtener PDFs de la carpeta
            $pdfs = File::files($carpeta);
            $pdfFiles = array_filter($pdfs, function($file) {
                return $file->getExtension() === 'pdf';
            });

            // Si es diseñador, filtrar solo PDFs de pedidos asignados
            if ($profileId == 2) { // Asumiendo que profile_id 2 es diseñador
                $pdfFiles = self::filtrarPdfsPorUsuario($pdfFiles, $userId);
            }

            // Si después del filtro no hay PDFs, no incluir la carpeta
            if (empty($pdfFiles)) {
                continue;
            }

            $carpetasConInfo[] = [
                'fecha' => $nombreCarpeta,
                'fecha_ordenable' => $fecha->format('Y-m-d'),
                'total_pdfs' => count($pdfFiles),
                'ruta' => $carpeta
            ];
        }

        // Ordenar por fecha más reciente primero
        usort($carpetasConInfo, function($a, $b) {
            return strcmp($b['fecha_ordenable'], $a['fecha_ordenable']);
        });

        // Implementar paginación manual
        $total = count($carpetasConInfo);
        $lastPage = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $carpetasPaginadas = array_slice($carpetasConInfo, $offset, $perPage);

        return [
            'data' => $carpetasPaginadas,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage
        ];
    }

    /**
     * Filtra PDFs según los pedidos asignados a un usuario diseñador
     */
    private static function filtrarPdfsPorUsuario($pdfFiles, $userId)
    {
        // Obtener todos los pedidos asignados al usuario
        $pedidosAsignados = DB::table('sales')
            ->where('user_id', $userId)
            ->pluck('id')
            ->toArray();

        if (empty($pedidosAsignados)) {
            return [];
        }

        // Filtrar PDFs que contengan el ID de venta asignado
        $pdfsFiltrados = array_filter($pdfFiles, function($file) use ($pedidosAsignados) {
            $nombre = $file->getFilename();

            // El nombre del archivo empieza con el ID de venta: {ventaId}-...
            foreach ($pedidosAsignados as $ventaId) {
                if (str_starts_with($nombre, $ventaId . '-')) {
                    return true;
                }
            }
            return false;
        });

        return array_values($pdfsFiltrados);
    }

    /**
     * Obtiene todos los PDFs de una fecha específica
     * Incluye PDFs de pedidos y de cintas (coser/planchar x24/x48)
     */
    public static function getPdfsDeUnaFecha($fecha, $userId = null, $profileId = null)
    {
        $carpetaPlanchas = storage_path("app/pdf/planchas/{$fecha}");
        $carpetaCintasCoser = storage_path("app/pdf/Cintas - Coser");
        $carpetaCintasPlanchar = storage_path("app/pdf/Cintas - Planchar");
        $carpetaBandas = storage_path("app/pdf/Bandas");
        $carpetaSellos = storage_path("app/pdf/Sellos");

        $resultado = [
            'fecha' => $fecha,
            'pdfs_pedidos' => [],
            'extras' => []
        ];

        // Obtener PDFs de pedidos
        if (is_dir($carpetaPlanchas)) {
            $pdfs = File::files($carpetaPlanchas);
            $pdfFiles = array_filter($pdfs, function($file) {
                return $file->getExtension() === 'pdf';
            });

            // Filtrar por usuario si es diseñador
            if ($profileId == 2) {
                $pdfFiles = self::filtrarPdfsPorUsuario($pdfFiles, $userId);
            }

            foreach ($pdfFiles as $pdf) {
                $resultado['pdfs_pedidos'][] = [
                    'nombre' => $pdf->getFilename(),
                    'ruta' => $pdf->getPathname(),
                    'tamanio' => $pdf->getSize(),
                    'fecha_modificacion' => date('Y-m-d H:i:s', $pdf->getMTime())
                ];
            }
        }

        // Obtener PDFs de cintas coser
        if (is_dir($carpetaCintasCoser)) {
            $cintaCoserX24 = "{$carpetaCintasCoser}/{$fecha}-coser-x24.pdf";
            $cintaCoserX48 = "{$carpetaCintasCoser}/{$fecha}-coser-x48.pdf";

            if (file_exists($cintaCoserX24)) {
                $resultado['extras'][] = [
                    'nombre' => basename($cintaCoserX24),
                    'ruta' => $cintaCoserX24,
                    'tamanio' => filesize($cintaCoserX24),
                    'fecha_modificacion' => date('Y-m-d H:i:s', filemtime($cintaCoserX24))
                ];
            }

            if (file_exists($cintaCoserX48)) {
                $resultado['extras'][] = [
                    'nombre' => basename($cintaCoserX48),
                    'ruta' => $cintaCoserX48,
                    'tamanio' => filesize($cintaCoserX48),
                    'fecha_modificacion' => date('Y-m-d H:i:s', filemtime($cintaCoserX48))
                ];
            }
        }

        // Obtener PDFs de cintas planchar
        if (is_dir($carpetaCintasPlanchar)) {
            $cintaPlancharX24 = "{$carpetaCintasPlanchar}/{$fecha}-planchar-x24.pdf";
            $cintaPlancharX48 = "{$carpetaCintasPlanchar}/{$fecha}-planchar-x48.pdf";

            if (file_exists($cintaPlancharX24)) {
                $resultado['extras'][] = [
                    'nombre' => basename($cintaPlancharX24),
                    'ruta' => $cintaPlancharX24,
                    'tamanio' => filesize($cintaPlancharX24),
                    'fecha_modificacion' => date('Y-m-d H:i:s', filemtime($cintaPlancharX24))
                ];
            }

            if (file_exists($cintaPlancharX48)) {
                $resultado['extras'][] = [
                    'nombre' => basename($cintaPlancharX48),
                    'ruta' => $cintaPlancharX48,
                    'tamanio' => filesize($cintaPlancharX48),
                    'fecha_modificacion' => date('Y-m-d H:i:s', filemtime($cintaPlancharX48))
                ];
            }
        }

        // Obtener PDF de bandas
        if (is_dir($carpetaBandas)) {
            $bandasPdf = "{$carpetaBandas}/{$fecha}-bandas.pdf";

            if (file_exists($bandasPdf)) {
                $resultado['extras'][] = [
                    'nombre' => basename($bandasPdf),
                    'ruta' => $bandasPdf,
                    'tamanio' => filesize($bandasPdf),
                    'fecha_modificacion' => date('Y-m-d H:i:s', filemtime($bandasPdf))
                ];
            }
        }

        // Obtener PDF de sellos
        if (is_dir($carpetaSellos)) {
            $sellosPdf = "{$carpetaSellos}/{$fecha}-sellos.pdf";

            if (file_exists($sellosPdf)) {
                $resultado['extras'][] = [
                    'nombre' => basename($sellosPdf),
                    'ruta' => $sellosPdf,
                    'tamanio' => filesize($sellosPdf),
                    'fecha_modificacion' => date('Y-m-d H:i:s', filemtime($sellosPdf))
                ];
            }
        }

        return $resultado;
    }

    /**
     * Obtiene la ruta completa de un PDF específico
     */
    public static function getRutaPdf($fecha, $nombrePdf)
    {
        // Buscar en carpeta de planchas
        $rutaPlanchas = storage_path("app/pdf/planchas/{$fecha}/{$nombrePdf}");
        if (file_exists($rutaPlanchas)) {
            return $rutaPlanchas;
        }

        // Buscar en cintas coser
        $rutaCintasCoser = storage_path("app/pdf/Cintas - Coser/{$nombrePdf}");
        if (file_exists($rutaCintasCoser)) {
            return $rutaCintasCoser;
        }

        // Buscar en cintas planchar
        $rutaCintasPlanchar = storage_path("app/pdf/Cintas - Planchar/{$nombrePdf}");
        if (file_exists($rutaCintasPlanchar)) {
            return $rutaCintasPlanchar;
        }

        // Buscar en bandas
        $rutaBandas = storage_path("app/pdf/Bandas/{$nombrePdf}");
        if (file_exists($rutaBandas)) {
            return $rutaBandas;
        }

        // Buscar en sellos
        $rutaSellos = storage_path("app/pdf/Sellos/{$nombrePdf}");
        if (file_exists($rutaSellos)) {
            return $rutaSellos;
        }

        return null;
    }
}