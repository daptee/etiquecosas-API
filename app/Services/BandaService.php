<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BandaService
{
    // IDs de productos de bandas
    const PRODUCTO_BANDA_1_ID = 52944;  // 1 banda (1 color)
    const PRODUCTO_BANDA_2_ID = 52796;  // 2 bandas (2 colores)

    // Lista de colores disponibles para las bandas
    const COLORES_DISPONIBLES = [
        'FUCSIA',
        'CELESTE',
        'TURQUESA',
        'ROSA',
        'AZUL',
        'VIOLETA'
    ];

    /**
     * Verifica si un producto es de tipo Banda
     */
    public static function esProductoBanda(int $productId): bool
    {
        return in_array($productId, [self::PRODUCTO_BANDA_1_ID, self::PRODUCTO_BANDA_2_ID]);
    }

    /**
     * Determina cuantas bandas tiene el producto (1 o 2)
     */
    public static function getCantidadBandas(int $productId): int
    {
        if ($productId === self::PRODUCTO_BANDA_1_ID) {
            return 1;
        } elseif ($productId === self::PRODUCTO_BANDA_2_ID) {
            return 2;
        }
        return 0;
    }

    /**
     * Agrega una banda al PDF consolidado del dia
     *
     * @param int $ventaId ID de la venta
     * @param object $productOrder Orden del producto (incluye variant con attributesvalues)
     * @param string $nombreCompleto Nombre del form (fallback)
     * @param array|string $customColor Color del customization_data (no usado, colores vienen de variante)
     * @param string|null $customIcon Icono elegido
     * @param mixed $fechaCompra Fecha de compra
     */
    public static function agregarBandaAlPdf(
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

        $dirPath = storage_path("app/pdf/Bandas");

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        // Obtener cantidad de bandas (1 o 2)
        $cantidadBandas = self::getCantidadBandas($productOrder->product_id);

        if ($cantidadBandas === 0) {
            Log::warning("Producto no es de tipo Banda", [
                'product_id' => $productOrder->product_id,
                'product_order_id' => $productOrder->id
            ]);
            return;
        }

        // El nombre viene del campo "comment" del productOrder
        $nombre = $productOrder->comment ?? $nombreCompleto;
        $nombre = trim($nombre);

        if (empty($nombre)) {
            Log::warning("Banda sin nombre", [
                'product_order_id' => $productOrder->id
            ]);
            return;
        }

        // Obtener colores de la variante (attributesvalues)
        $colores = self::obtenerColoresDeVariante($productOrder, $cantidadBandas);

        if (empty($colores)) {
            Log::warning("No se encontraron colores en la variante para banda", [
                'product_order_id' => $productOrder->id
            ]);
            return;
        }

        // Archivo JSON para almacenar las bandas del dia
        $jsonFile = "{$dirPath}/{$fechaCarpeta}-bandas.json";

        // Cargar bandas existentes o crear array vacio
        $bandas = [];
        if (file_exists($jsonFile)) {
            $bandas = json_decode(file_get_contents($jsonFile), true) ?? [];
        }

        // Agregar la nueva banda a cada color correspondiente
        $cantidad = $productOrder->quantity ?? 1;

        foreach ($colores as $colorNombre) {
            // Normalizar el nombre del color
            $colorNombre = strtoupper(trim($colorNombre));

            // Verificar que el color esta en la lista de disponibles
            if (!in_array($colorNombre, self::COLORES_DISPONIBLES)) {
                Log::warning("Color no reconocido para banda", [
                    'color' => $colorNombre,
                    'product_order_id' => $productOrder->id
                ]);
                continue;
            }

            $banda = [
                'venta_id' => $ventaId,
                'product_order_id' => $productOrder->id,
                'nombre' => $nombre,
                'cantidad' => $cantidad,
                'color_nombre' => $colorNombre,
                'icono' => $customIcon,
            ];

            $bandas[] = $banda;
        }

        // Guardar JSON actualizado
        file_put_contents($jsonFile, json_encode($bandas, JSON_PRETTY_PRINT));

        // Regenerar el PDF
        self::generarPdfConsolidado($fechaCarpeta, $bandas);

        Log::info("Banda agregada al PDF de Bandas", [
            'fecha' => $fechaCarpeta,
            'nombre' => $nombre,
            'cantidad' => $cantidad,
            'colores' => $colores
        ]);
    }

    /**
     * Obtiene los colores de la variante del producto
     * Los colores vienen en attributesvalues con atributos "Color banda 1" y "Color banda 2"
     */
    private static function obtenerColoresDeVariante($productOrder, int $cantidadBandas): array
    {
        $colores = [];

        // Acceder a la variante - puede ser un objeto o ya estar serializado
        $variantModel = $productOrder->variant;

        if (!$variantModel) {
            Log::warning("BandaService: No hay variante en productOrder", [
                'product_order_id' => $productOrder->id
            ]);
            return $colores;
        }

        // El campo 'variant' es un JSON que contiene attributesvalues
        // Puede venir como array (si ya fue casteado) o necesitar decodificacion
        $variantData = $variantModel->variant;

        if (is_string($variantData)) {
            $variantData = json_decode($variantData, true);
        }

        if (!$variantData) {
            Log::warning("BandaService: variant data vacio", [
                'product_order_id' => $productOrder->id
            ]);
            return $colores;
        }

        // Obtener attributesvalues del JSON de la variante
        $attributesvaluesRaw = $variantData['attributesvalues'] ?? [];

        // Verificar si los attributesvalues tienen datos completos o solo IDs
        // Si solo tienen 'id' sin 'value' o 'attribute', necesitamos cargar de la BD
        $needsLoad = empty($attributesvaluesRaw);
        if (!$needsLoad && isset($attributesvaluesRaw[0])) {
            // Verificar si el primer elemento tiene solo 'id' o es un numero
            $firstItem = $attributesvaluesRaw[0];
            if (is_numeric($firstItem) || (is_array($firstItem) && !isset($firstItem['value']))) {
                $needsLoad = true;
            }
        }

        if ($needsLoad) {
            // Usar el accessor que carga los datos completos desde la BD
            $attributesCollection = $variantModel->attributes_values;
            if ($attributesCollection && $attributesCollection->count() > 0) {
                $attributesvalues = $attributesCollection->map(function($attr) {
                    return [
                        'value' => $attr->value,
                        'attribute' => [
                            'name' => $attr->attribute->name ?? ''
                        ]
                    ];
                })->toArray();
            } else {
                $attributesvalues = [];
            }
        } else {
            $attributesvalues = $attributesvaluesRaw;
        }

        Log::info("BandaService: attributesvalues procesados", [
            'product_order_id' => $productOrder->id,
            'needsLoad' => $needsLoad,
            'attributesvalues' => $attributesvalues
        ]);

        // Buscar los atributos de color
        $colorBanda1 = null;
        $colorBanda2 = null;

        foreach ($attributesvalues as $attr) {
            $attrName = $attr['attribute']['name'] ?? '';
            $value = $attr['value'] ?? '';

            if (stripos($attrName, 'Color banda 1') !== false) {
                $colorBanda1 = $value;
            } elseif (stripos($attrName, 'Color banda 2') !== false) {
                $colorBanda2 = $value;
            } elseif (stripos($attrName, 'Color') !== false && $colorBanda1 === null) {
                // Fallback para atributo generico "Color"
                $colorBanda1 = $value;
            }
        }

        // Agregar colores encontrados
        if ($colorBanda1) {
            $colores[] = $colorBanda1;
        }
        if ($colorBanda2 && $cantidadBandas > 1) {
            $colores[] = $colorBanda2;
        }

        return $colores;
    }

    /**
     * Genera el PDF consolidado con todas las bandas del dia
     * Agrupa por color y genera una pagina por cada color
     */
    private static function generarPdfConsolidado(string $fecha, array $bandas): void
    {
        $dirPath = storage_path("app/pdf/Bandas");
        $pdfFile = "{$dirPath}/{$fecha}-bandas.pdf";

        // Agrupar bandas por color
        $bandasPorColor = [];
        foreach (self::COLORES_DISPONIBLES as $color) {
            $bandasPorColor[$color] = [];
        }

        foreach ($bandas as $banda) {
            $colorNombre = $banda['color_nombre'] ?? null;
            if ($colorNombre && isset($bandasPorColor[$colorNombre])) {
                $bandasPorColor[$colorNombre][] = $banda;
            }
        }

        // Filtrar colores sin bandas
        $bandasPorColor = array_filter($bandasPorColor, function($items) {
            return count($items) > 0;
        });

        if (empty($bandasPorColor)) {
            Log::warning("No hay bandas para generar PDF", ['fecha' => $fecha]);
            return;
        }

        $pdf = Pdf::loadView('bandas.consolidado', [
            'bandasPorColor' => $bandasPorColor,
            'fecha' => $fecha
        ])
        ->setPaper('a4', 'portrait')
        ->setOption('isRemoteEnabled', true)
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isFontSubsettingEnabled', true)
        ->setOption('fontDir', public_path('fonts'))
        ->setOption('fontCache', storage_path('fonts'))
        ->setOption('defaultFont', 'QuicksandBook');

        $pdf->save($pdfFile);

        Log::info("PDF consolidado de Bandas generado", [
            'path' => $pdfFile,
            'colores_con_bandas' => array_keys($bandasPorColor),
            'total_bandas' => count($bandas)
        ]);
    }

    /**
     * Elimina las bandas de una venta especifica del PDF del dia
     */
    public static function limpiarBandasDeVenta(int $ventaId, $fechaCompra = null): void
    {
        $fechaCarpeta = $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');

        $dirPath = storage_path("app/pdf/Bandas");
        $jsonFile = "{$dirPath}/{$fechaCarpeta}-bandas.json";

        if (file_exists($jsonFile)) {
            $bandas = json_decode(file_get_contents($jsonFile), true) ?? [];

            // Filtrar las bandas que NO son de esta venta
            $bandasFiltradas = array_filter($bandas, function($banda) use ($ventaId) {
                return $banda['venta_id'] != $ventaId;
            });

            $bandasFiltradas = array_values($bandasFiltradas);

            if (count($bandasFiltradas) > 0) {
                file_put_contents($jsonFile, json_encode($bandasFiltradas, JSON_PRETTY_PRINT));
                self::generarPdfConsolidado($fechaCarpeta, $bandasFiltradas);
            } else {
                // Si no quedan bandas, eliminar archivos
                unlink($jsonFile);
                $pdfFile = "{$dirPath}/{$fechaCarpeta}-bandas.pdf";
                if (file_exists($pdfFile)) {
                    unlink($pdfFile);
                }
            }

            Log::info("Bandas de venta eliminadas", [
                'venta_id' => $ventaId,
                'fecha' => $fechaCarpeta
            ]);
        }
    }
}
