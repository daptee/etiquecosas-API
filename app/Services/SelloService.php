<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SelloService
{
    // ID del producto de sellos personalizados
    const PRODUCTO_SELLO_ID = 481;

    /**
     * Verifica si un producto es de tipo Sello Personalizado
     */
    public static function esProductoSello(int $productId): bool
    {
        return $productId === self::PRODUCTO_SELLO_ID;
    }

    /**
     * Agrega un sello al PDF consolidado del dia
     *
     * @param int $ventaId ID de la venta
     * @param object $productOrder Orden del producto
     * @param string $nombreCompleto Nombre completo del cliente
     * @param mixed $customColor Color elegido (no se usa en sellos, siempre negro)
     * @param string|null $customIcon Icono elegido
     * @param mixed $fechaCompra Fecha de compra
     */
    public static function agregarSelloAlPdf(
        int $ventaId,
        $productOrder,
        string $nombreCompleto,
        $customColor,
        ?string $customIcon,
        $fechaCompra = null
    ): void {
        $fechaCarpeta = $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');

        $dirPath = storage_path("app/pdf/Sellos");

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        // Archivo JSON para almacenar los sellos del dia
        $jsonFile = "{$dirPath}/{$fechaCarpeta}-sellos.json";

        // Cargar sellos existentes o crear array vacio
        $sellos = [];
        if (file_exists($jsonFile)) {
            $sellos = json_decode(file_get_contents($jsonFile), true) ?? [];
        }

        // Agregar el nuevo sello
        $cantidad = $productOrder->quantity ?? 1;

        $sello = [
            'venta_id' => $ventaId,
            'product_order_id' => $productOrder->id,
            'nombre' => $nombreCompleto,
            'cantidad' => $cantidad,
            'icono' => $customIcon, // null si es "Sin dibujo"
        ];

        $sellos[] = $sello;

        // Guardar JSON actualizado
        file_put_contents($jsonFile, json_encode($sellos, JSON_PRETTY_PRINT));

        // Regenerar el PDF
        self::generarPdfConsolidado($fechaCarpeta, $sellos);

        Log::info("Sello agregado al PDF de Sellos", [
            'fecha' => $fechaCarpeta,
            'nombre' => $nombreCompleto,
            'cantidad' => $cantidad,
            'con_icono' => $customIcon !== null
        ]);
    }

    /**
     * Genera el PDF consolidado con todos los sellos del dia
     */
    private static function generarPdfConsolidado(string $fecha, array $sellos): void
    {
        $dirPath = storage_path("app/pdf/Sellos");
        $pdfFile = "{$dirPath}/{$fecha}-sellos.pdf";

        $pdf = Pdf::loadView('sellos.consolidado', [
            'sellos' => $sellos,
            'fecha' => $fecha
        ])->setPaper('a4', 'portrait');

        $dompdf = $pdf->getDomPDF();
        $dompdf->getOptions()->setFontDir(public_path('fonts'));
        $dompdf->getOptions()->setFontCache(storage_path('fonts_cache'));

        $pdf->save($pdfFile);

        Log::info("PDF consolidado de Sellos generado", [
            'path' => $pdfFile,
            'total_sellos' => count($sellos)
        ]);
    }

    /**
     * Elimina los sellos de una venta especifica del PDF del dia
     */
    public static function limpiarSellosDeVenta(int $ventaId, $fechaCompra = null): void
    {
        $fechaCarpeta = $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');

        $dirPath = storage_path("app/pdf/Sellos");
        $jsonFile = "{$dirPath}/{$fechaCarpeta}-sellos.json";

        if (file_exists($jsonFile)) {
            $sellos = json_decode(file_get_contents($jsonFile), true) ?? [];

            // Filtrar los sellos que NO son de esta venta
            $sellosFiltrados = array_filter($sellos, function($sello) use ($ventaId) {
                return $sello['venta_id'] != $ventaId;
            });

            $sellosFiltrados = array_values($sellosFiltrados);

            if (count($sellosFiltrados) > 0) {
                file_put_contents($jsonFile, json_encode($sellosFiltrados, JSON_PRETTY_PRINT));
                self::generarPdfConsolidado($fechaCarpeta, $sellosFiltrados);
            } else {
                // Si no quedan sellos, eliminar archivos
                unlink($jsonFile);
                $pdfFile = "{$dirPath}/{$fechaCarpeta}-sellos.pdf";
                if (file_exists($pdfFile)) {
                    unlink($pdfFile);
                }
            }

            Log::info("Sellos de venta eliminados", [
                'venta_id' => $ventaId,
                'fecha' => $fechaCarpeta
            ]);
        }
    }
}
