<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CintaPlancharService
{
    // Producto ID para "Etiquetas de tela PARA PLANCHAR"
    const PRODUCTO_PLANCHAR_ID = 1247;

    // Productos Combo que también generan PDFs planchables
    const PRODUCTO_COMBO_PRIMARIA_ID = 79;
    const PRODUCTO_COMBO_MATERNAL_ID = 141;

    // Producto ID para "Cintas para pegar" (6x2.5cm) - va en PDF x24
    const PRODUCTO_PEGAR_ID = 92909;

    // Variantes: 801 = 24 etiquetas, 802 = 48 etiquetas
    const VARIANTE_24 = 801;
    const VARIANTE_48 = 802;

    /**
     * Verifica si un producto es de tipo "Etiquetas de tela PARA PLANCHAR"
     * o si es un Combo (primaria/maternal) que también genera planchables
     * o si es "Cintas para pegar"
     */
    public static function esProductoPlanchar(int $productId): bool
    {
        return in_array($productId, [
            self::PRODUCTO_PLANCHAR_ID,
            self::PRODUCTO_COMBO_PRIMARIA_ID,
            self::PRODUCTO_COMBO_MATERNAL_ID,
            self::PRODUCTO_PEGAR_ID
        ]);
    }

    /**
     * Verifica si un producto es "Cintas para pegar" (etiqueta grande 6x2.5cm)
     */
    public static function esProductoPegar(int $productId): bool
    {
        return $productId === self::PRODUCTO_PEGAR_ID;
    }

    /**
     * Obtiene el tipo de PDF (x24 o x48) según la variante o producto
     */
    public static function getTipoPorVariante($variantId, $productId = null): ?string
    {
        // Los productos Combo (primaria/maternal) y Pegar siempre son x24
        if ($productId && in_array($productId, [
            self::PRODUCTO_COMBO_PRIMARIA_ID,
            self::PRODUCTO_COMBO_MATERNAL_ID,
            self::PRODUCTO_PEGAR_ID
        ])) {
            return 'x24';
        }

        if ($variantId == self::VARIANTE_24) {
            return 'x24';
        } elseif ($variantId == self::VARIANTE_48) {
            return 'x48';
        }
        return null;
    }

    /**
     * Agrega una etiqueta al PDF consolidado del día
     */
    public static function agregarEtiquetaAlPdf(
        int $ventaId,
        $productOrder,
        string $nombreCompleto,
        $customColor,
        ?string $customIcon,
        $fechaCompra = null
    ): void {
        // Normalizar color: puede venir como array o string
        if (is_array($customColor)) {
            $customColor = $customColor[0] ?? '#000000';
        }
        $customColor = $customColor ?? '#000000';

        $fechaCarpeta = $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');
        $dirPath = storage_path("app/pdf/Cintas - Planchar");

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        // Obtener variante ID y product ID del producto
        $variantId = $productOrder->variant_id;
        $productId = $productOrder->product_id ?? null;

        $tipo = self::getTipoPorVariante($variantId, $productId);

        Log::info("CintaPlancharService - Procesando producto", [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'tipo' => $tipo,
            'es_pegar' => self::esProductoPegar($productId)
        ]);

        if (!$tipo) {
            Log::warning("No se pudo determinar el tipo (x24/x48) para el producto planchar", [
                'variant_id' => $variantId,
                'product_order_id' => $productOrder->id
            ]);
            return;
        }

        // Archivo JSON para almacenar las etiquetas del día
        $jsonFile = "{$dirPath}/{$fechaCarpeta}-{$tipo}.json";

        // Cargar etiquetas existentes o crear array vacío
        $etiquetas = [];
        if (file_exists($jsonFile)) {
            $etiquetas = json_decode(file_get_contents($jsonFile), true) ?? [];
        }

        // Agregar la nueva etiqueta
        $cantidad = $productOrder->quantity ?? 1;
        $esGrande = self::esProductoPegar($productId); // Etiqueta 6x2.5cm

        $etiqueta = [
            'venta_id' => $ventaId,
            'product_order_id' => $productOrder->id,
            'nombre' => $nombreCompleto,
            'cantidad' => $cantidad,
            'color' => $customColor,
            'icono' => $customIcon,
            'grande' => $esGrande,
        ];

        $etiquetas[] = $etiqueta;

        // Guardar JSON actualizado
        file_put_contents($jsonFile, json_encode($etiquetas, JSON_PRETTY_PRINT));

        // Regenerar el PDF
        self::generarPdfConsolidado($fechaCarpeta, $tipo, $etiquetas);

        Log::info("Etiqueta agregada al PDF de Cintas - Planchar", [
            'fecha' => $fechaCarpeta,
            'tipo' => $tipo,
            'nombre' => $nombreCompleto,
            'cantidad' => $cantidad
        ]);
    }

    /**
     * Genera el PDF consolidado con todas las etiquetas del día
     */
    private static function generarPdfConsolidado(string $fecha, string $tipo, array $etiquetas): void
    {
        $dirPath = storage_path("app/pdf/Cintas - Planchar");
        $pdfFile = "{$dirPath}/{$fecha}-planchar-{$tipo}.pdf";

        $pdf = Pdf::loadView('cintas-planchar.consolidado', [
            'etiquetas' => $etiquetas,
            'fecha' => $fecha,
            'tipo' => $tipo
        ])->setPaper('a4', 'portrait');

        $dompdf = $pdf->getDomPDF();
        $dompdf->getOptions()->setFontDir(public_path('fonts'));
        $dompdf->getOptions()->setFontCache(storage_path('fonts_cache'));

        $pdf->save($pdfFile);

        Log::info("PDF consolidado de Cintas - Planchar generado", [
            'path' => $pdfFile,
            'total_etiquetas' => count($etiquetas)
        ]);
    }

    /**
     * Elimina las etiquetas de una venta específica del PDF del día
     */
    public static function limpiarEtiquetasDeVenta(int $ventaId, $fechaCompra = null): void
    {
        $fechaCarpeta = $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');
        $dirPath = storage_path("app/pdf/Cintas - Planchar");

        foreach (['x24', 'x48'] as $tipo) {
            $jsonFile = "{$dirPath}/{$fechaCarpeta}-{$tipo}.json";

            if (file_exists($jsonFile)) {
                $etiquetas = json_decode(file_get_contents($jsonFile), true) ?? [];

                // Filtrar las etiquetas que NO son de esta venta
                $etiquetasFiltradas = array_filter($etiquetas, function($etiqueta) use ($ventaId) {
                    return $etiqueta['venta_id'] != $ventaId;
                });

                $etiquetasFiltradas = array_values($etiquetasFiltradas);

                if (count($etiquetasFiltradas) > 0) {
                    file_put_contents($jsonFile, json_encode($etiquetasFiltradas, JSON_PRETTY_PRINT));
                    self::generarPdfConsolidado($fechaCarpeta, $tipo, $etiquetasFiltradas);
                } else {
                    // Si no quedan etiquetas, eliminar archivos
                    unlink($jsonFile);
                    $pdfFile = "{$dirPath}/{$fechaCarpeta}-planchar-{$tipo}.pdf";
                    if (file_exists($pdfFile)) {
                        unlink($pdfFile);
                    }
                }

                Log::info("Etiquetas de venta eliminadas de Cintas - Planchar", [
                    'venta_id' => $ventaId,
                    'fecha' => $fechaCarpeta,
                    'tipo' => $tipo
                ]);
            }
        }
    }
}
