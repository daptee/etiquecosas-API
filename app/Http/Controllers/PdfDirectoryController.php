<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleProduct;
use App\Services\PdfDirectoryService;
use App\Services\ProductPdfResolverService;
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

            $metaData = [
                'current_page' => $resultado['current_page'],
                'last_page' => $resultado['last_page'],
                'per_page' => $resultado['per_page'],
                'total' => $resultado['total'],
                'from' => $resultado['total'] > 0 ? (($resultado['current_page'] - 1) * $resultado['per_page']) + 1 : null,
                'to' => $resultado['total'] > 0 ? min($resultado['current_page'] * $resultado['per_page'], $resultado['total']) : null,
            ];

            return $this->success(
                $resultado['data'],
                'Carpetas de PDF obtenidas exitosamente',
                $metaData
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
    public function getPdfsByDate(Request $request, $fecha)
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

            // Filtro por user_id (solo para admin)
            $filterUserId = $request->input('user_id');

            $userId = null;
            $profileIdFiltro = null;

            if ($profileId == 1 && $filterUserId) {
                // Admin filtrando por usuario específico
                $userId = $filterUserId;
                $profileIdFiltro = 2; // Filtrar como diseñador
            } elseif ($profileId == 2) {
                // Si es diseñador, solo ver sus PDFs
                $userId = $user->id;
                $profileIdFiltro = 2;
            }

            $resultado = PdfDirectoryService::getPdfsDeUnaFecha($fecha, $userId, $profileIdFiltro);

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

            // Agregar PDFs extras (cintas, bandas, sellos) en carpetas separadas
            if (!empty($pdfsData['extras'])) {
                foreach ($pdfsData['extras'] as $extra) {
                    if (file_exists($extra['ruta'])) {
                        $nombre = $extra['nombre'];

                        // Determinar la carpeta según el tipo de archivo
                        if (strpos($nombre, '-coser-') !== false) {
                            $carpeta = 'cintas_coser';
                        } elseif (strpos($nombre, '-planchar-') !== false) {
                            $carpeta = 'cintas_planchar';
                        } elseif (strpos($nombre, '-bandas.pdf') !== false) {
                            $carpeta = 'bandas';
                        } elseif (strpos($nombre, '-sellos.pdf') !== false) {
                            $carpeta = 'sellos';
                        } else {
                            $carpeta = 'extras';
                        }

                        $zip->addFile(
                            $extra['ruta'],
                            $carpeta . '/' . $nombre
                        );
                        $archivosAgregados++;
                    }
                }
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
            if ($profileId == 2 && !$this->ventaDelNombrePdfPerteneceAUsuario($nombrePdf, $user->id)) {
                return $this->error(
                    'No tiene permisos para descargar este PDF',
                    403
                );
            }

            return response()->download($rutaPdf);
        } catch (\Exception $e) {
            return $this->error(
                'Error al descargar el PDF: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Muestra un PDF específico en el navegador (vista previa), en vez de
     * forzar la descarga — mismo archivo/permisos que downloadPdf(), la
     * única diferencia es `response()->file()` en vez de
     * `response()->download()` (sin `Content-Disposition: attachment`).
     */
    public function viewPdf($fecha, $nombrePdf)
    {
        try {
            $user = Auth::user();
            $profileId = $user->profile_id;

            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $fecha)) {
                return $this->error('Formato de fecha inválido. Use dd-mm-yyyy', 400);
            }

            $rutaPdf = PdfDirectoryService::getRutaPdf($fecha, $nombrePdf);

            if (!$rutaPdf || !file_exists($rutaPdf)) {
                return $this->error('PDF no encontrado', 404);
            }

            if ($profileId == 2 && !$this->ventaDelNombrePdfPerteneceAUsuario($nombrePdf, $user->id)) {
                return $this->error('No tiene permisos para ver este PDF', 403);
            }

            return response()->file($rutaPdf);
        } catch (\Exception $e) {
            return $this->error('Error al mostrar el PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Regenera UN PDF puntual del listado de "pdfs_pedidos" (no aplica a
     * "extras" — cintas/bandas/sellos son archivos consolidados por fecha,
     * sin una línea de venta propia). El nombre ya trae
     * "{ventaId}-{salesProductId}-..." (ver nota de
     * PdfDirectoryService::resolveDesignIdFromFilename()) — de ahí se
     * reconstruye la línea de venta y se vuelve a correr
     * ProductPdfResolverService::resolveAndGenerate(), el mismo punto único
     * que usa una venta real (toma el diseño del editor vinculado HOY a ese
     * producto+variante si hay uno, o cae al sistema legacy si no).
     * Solo borra los archivos de ESTA línea de venta puntual
     * ("{ventaId}-{salesProductId}-*.pdf"), no todo el pedido.
     */
    public function regeneratePdf($fecha, $nombrePdf)
    {
        try {
            $user = Auth::user();
            $profileId = $user->profile_id;

            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $fecha)) {
                return $this->error('Formato de fecha inválido. Use dd-mm-yyyy', 400);
            }

            if (!preg_match('/^(\d+)-(\d+)-/', $nombrePdf, $matches)) {
                return $this->error('No se pudo determinar el pedido/producto de este archivo', 422);
            }
            [$_, $ventaId, $salesProductId] = $matches;

            $sale = Sale::find($ventaId);
            if (!$sale) {
                return $this->error('La venta de este PDF ya no existe', 404);
            }

            if ($profileId == 2 && $sale->user_id != $user->id) {
                return $this->error('No tiene permisos para regenerar este PDF', 403);
            }

            $productOrder = SaleProduct::with('product', 'variant')->find($salesProductId);
            if (!$productOrder || $productOrder->sale_id != $sale->id) {
                return $this->error('No se encontró la línea de venta de este PDF', 404);
            }

            $customData = json_decode($productOrder->customization_data, true) ?? [];
            $form = $customData['form'] ?? [];
            $nombreCompleto = trim(($form['name'] ?? '') . ' ' . ($form['lastName'] ?? ''));
            $customColor = $customData['color']['color_code'] ?? null;
            $customIcon = $customData['icon']['icon'] ?? null;
            if ($customIcon && ($customData['icon']['name'] ?? null) == 'Sin dibujo') {
                // '' (no null): personalización explícita de "ningún ícono" —
                // ver nota en EtiquetaService::resolverElementoDesign.
                $customIcon = '';
            }

            // Solo los archivos de ESTA línea de venta — no limpiarPdfsDelPedido()
            // (eso borraría los de TODAS las líneas de este mismo pedido).
            $dirPath = storage_path("app/pdf/planchas/{$fecha}");
            foreach (glob("{$dirPath}/{$ventaId}-{$salesProductId}-*.pdf") ?: [] as $file) {
                @unlink($file);
            }

            $paths = ProductPdfResolverService::resolveAndGenerate(
                (int) $ventaId,
                $productOrder,
                $nombreCompleto,
                $form,
                $customColor,
                $customIcon,
                $sale->created_at
            );

            if (empty($paths)) {
                return $this->error('No se pudo regenerar el PDF — revisá que el producto tenga un diseño o temática asociada', 500);
            }

            // El flujo legacy (resolveLegacy() dentro de resolveAndGenerate)
            // puede devolver un array CON UN SUB-ARRAY adentro (empuja el
            // array que ya devuelve generarEtiquetas(), no sus elementos) —
            // se aplana para no romper acá por ese detalle de implementación.
            $flatPaths = [];
            array_walk_recursive($paths, function ($p) use (&$flatPaths) {
                $flatPaths[] = $p;
            });

            return $this->success(
                ['paths' => array_map('basename', $flatPaths)],
                'PDF regenerado correctamente'
            );
        } catch (\Throwable $e) {
            return $this->error('Error al regenerar el PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Para un diseñador (profile_id 2): si el nombre del archivo trae un id
     * de venta válido, confirma que esa venta está asignada a este usuario.
     * Sin id de venta detectable en el nombre, no bloquea (mismo criterio
     * ya usado en downloadPdf()).
     */
    private function ventaDelNombrePdfPerteneceAUsuario(string $nombrePdf, int $userId): bool
    {
        preg_match('/^(\d+)-/', $nombrePdf, $matches);
        if (empty($matches[1])) {
            return true;
        }

        return \DB::table('sales')
            ->where('id', $matches[1])
            ->where('user_id', $userId)
            ->exists();
    }
}