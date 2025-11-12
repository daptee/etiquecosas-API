<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EtiquetaService
{
    /**
     * 🗑️ Elimina todos los PDFs existentes de un pedido específico
     */
    public static function limpiarPdfsDelPedido(int $ventaId, $fechaCompra = null): void
    {
        $fechaCarpeta = $fechaCompra ? date('d-m-Y', strtotime($fechaCompra)) : date('d-m-Y');
        $dirPath = storage_path("app/pdf/planchas/{$fechaCarpeta}");

        if (!is_dir($dirPath)) {
            return; // No hay carpeta, no hay nada que eliminar
        }

        $existingFiles = glob("{$dirPath}/{$ventaId}-*.pdf");
        if (!empty($existingFiles)) {
            foreach ($existingFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                    Log::info("🗑️ PDF anterior eliminado", ['path' => $file]);
                }
            }
            Log::info("🔄 Eliminados " . count($existingFiles) . " PDFs anteriores del pedido {$ventaId}");
        }
    }

    public static function generarEtiquetas(int $ventaId, $tematicaId, array $nombres, $productOrder, $tematicaCoincidente, $customColor, $customIcon, $fechaCompra = null): array
    {
        $logo = "https://api.etiquecosaslab.com.ar/icons/mail/etiquecosas_logo-rosa.png";
        $outputFiles = [];
        $fechaCarpeta = $fechaCompra ? date('d-m-Y', strtotime($fechaCompra)) : date('d-m-Y');
        $dirPath = storage_path("app/pdf/planchas/{$fechaCarpeta}");
        if (!is_dir($dirPath)) mkdir($dirPath, 0755, true);

        $pdf = $tematicaCoincidente['pdf'] ?? null;
        $tematicaName = $tematicaCoincidente['name'] ?? null;
        $colorRange = $tematicaCoincidente['color-range'] ?? null;
        $imagesPdf = $tematicaCoincidente['images'] ?? null;
        $urlPdf = $tematicaCoincidente['pdf-url'] ?? null;
        $typography = $tematicaCoincidente['typography'] ?? null;
        $numberLabels = $tematicaCoincidente['number-labels'] ?? null;

        /**
         * 🔧 Helper para obtener vistas según tipo o URL personalizada
         */
        $getViews = function ($pdf, string $prefix, $urlPdf = null) use ($customIcon) {
            // 🟣 Si llegan URLs personalizadas desde la web
            if ($urlPdf) {
                // Acepta una o varias rutas
                $urls = is_array($urlPdf) ? $urlPdf : [$urlPdf];
                // Asegura el prefijo "tematica/" si no lo tiene
                return array_map(function ($u) use ($customIcon) {
                    $path = str_starts_with($u, 'tematica/') ? $u : "tematica/{$u}";

                    // 🔄 Si no hay icono y la ruta contiene "PERSONALIZABLE", cambiar a "PERSONALIZABLE SIN ICONO"
                    if (!$customIcon && str_contains($path, '/PERSONALIZABLE')) {
                        $path = str_replace('/PERSONALIZABLE', '/PERSONALIZABLE SIN ICONO', $path);
                    }

                    return $path;
                }, $urls);
            }

            // 🟢 Map clásico de PDFs
            /* $map = [
                'Etiquetas maxi, verticales, super-maxi, super-mini' => "tematica/principal/$prefix",
                'Etiquetas vinilo' => "tematica/vinilo/$prefix",
                'Etiquetas super-mini' => "tematica/super-mini/$prefix",
                'Etiquetas super-maxi' => "tematica/super-maxi/$prefix",
                'Etiquetas maxi' => "tematica/maxi/$prefix",
                'Etiquetas spot and maxi' => "tematica/spot-and-maxi/$prefix",
                'Etiquetas maxi and super maxi and super mini' => "tematica/maxi-and-super-maxi-and-super-mini/$prefix",
                'Etiquetas planchables' => "tematica/planchable/$prefix",
                'Etiquetas transfer' => "tematica/transfer/$prefix",
            ];

            if ($pdf) {
                return array_values(array_intersect_key($map, array_flip($pdf)));
            } */

            // 🟠 Vistas por defecto si no hay coincidencias
            return [
                "tematica/principal/$prefix",
                "tematica/vinilo/$prefix",
                "tematica/super-mini/$prefix",
            ];
        };

        /**
         * 🧩 Helper para generar y guardar un PDF
         */
        $renderPdf = function ($view, $plantilla, $product_order, $filePath) use (&$outputFiles) {
            try {
                $pdf = Pdf::loadView($view, compact('plantilla', 'product_order'))->setPaper('a4', 'portrait');
                $dompdf = $pdf->getDomPDF();
                $dompdf->getOptions()->setFontDir(public_path('fonts'));
                $dompdf->getOptions()->setFontCache(storage_path('fonts_cache'));
                $pdf->save($filePath);
                $outputFiles[] = $filePath;
                Log::info("✅ PDF generado", ['path' => $filePath]);
            } catch (\Throwable $e) {
                Log::error("❌ Error generando PDF", [
                    'view' => $view,
                    'error' => $e->getMessage(),
                ]);
            }
        };

        /**
         * 🟣 CASO 1: PDF PERSONALIZABLE (CON ICONO)
         */
        if ($customIcon && $customColor) {
            Log::info("🟣 Generando PDF PERSONALIZABLE con icono");
            $views = $getViews($pdf, "PERSONALIZABLE", $urlPdf);
            $customIconPath = public_path($customIcon);

            foreach ($nombres as $nombre) {
                $fontClass = mb_strlen($nombre, 'UTF-8') > 16 ? 'small-text-size' : 'normal-text-size';
                $plantilla = [
                    'colores' => $customColor[0],
                    'imagen' => $customIconPath,
                    'fontClass' => $fontClass,
                    'columna' => 2,
                    'filas' => 19,
                    'label' => $numberLabels ?? 24
                ];
                $product_order = (object)['name' => $nombre, 'order' => (object)['id_external' => $ventaId]];

                foreach ($views as $i => $view) {
                    $filePath = "{$dirPath}/{$ventaId}-{$productOrder->id}-{$productOrder->product->name}-PERSONALIZABLE-" . ($i + 1) . ".pdf";
                    $renderPdf($view, $plantilla, $product_order, $filePath);
                }
            }
            return $outputFiles;
        }

        /**
         * 🟣 CASO 1B: PDF PERSONALIZABLE SIN ICONO (LISA)
         */
        if (!$customIcon && $customColor) {
            Log::info("🟣 Generando PDF PERSONALIZABLE sin icono (lisa)");
            $views = $getViews($pdf, "PERSONALIZABLE SIN ICONO", $urlPdf);

            foreach ($nombres as $nombre) {
                $fontClass = mb_strlen($nombre, 'UTF-8') > 16 ? 'small-text-size' : 'normal-text-size';
                
                $plantilla = [
                    'colores' => $customColor[0],
                    'fontClass' => $fontClass,
                    'columna' => 2,
                    'filas' => 19,
                    'label' => $numberLabels ?? 24
                ];
                $product_order = (object)['name' => $nombre, 'order' => (object)['id_external' => $ventaId]];

                foreach ($views as $i => $view) {
                    $filePath = "{$dirPath}/{$ventaId}-{$productOrder->id}-{$productOrder->product->name}-PERSONALIZABLE_SIN_ICONO-" . ($i + 1) . ".pdf";
                    $renderPdf($view, $plantilla, $product_order, $filePath);
                }
            }
            return $outputFiles;
        }

        /**
         * 🟢 CASO 2: PDF GAMA DE COLORES
         */
        if ($colorRange) {
            Log::info("🟢 Generando PDF GAMA DE COLORES");
            $views = $getViews($pdf, "COLOR RANGE", $urlPdf);

            foreach ($nombres as $nombre) {
                $isWhiteAndBlack = $tematicaName === 'Blanco y Negro';
                $isWhite = $tematicaName === 'Blanco' || $tematicaName === 'Blanco y Negro' ? true : null;
                $fontClass = mb_strlen($nombre, 'UTF-8') > 16 ? 'small-text-size' : 'normal-text-size';

                $plantilla = [
                    'colores' => $isWhiteAndBlack ? ["#FFF", "#FFF", "#FFF"] : $colorRange,
                    'color' => $colorRange,
                    'images' => $imagesPdf ? array_map(fn($img) => storage_path("app/pdf/Iconos/Tematicas/$img"), $imagesPdf) : [],
                    'colorText' => $isWhiteAndBlack ? '#000' : '#fff',
                    'fontClass' => $fontClass,
                    'fontSize' => $typography ? self::getFontSize($nombre, $typography) : null,
                    'logo' => $logo,
                    'filas' => 19,
                    'label' => $numberLabels ?? 24,
                    'isWhite' => $isWhite ?? null
                ];

                $product_order = (object)['name' => $nombre, 'order' => (object)['id_external' => $ventaId]];

                foreach ($views as $i => $view) {
                    $filePath = "{$dirPath}/{$ventaId}-{$productOrder->id}-{$productOrder->product->name}-COLOR_RANGE-" . ($i + 1) . ".pdf";
                    $renderPdf($view, $plantilla, $product_order, $filePath);
                }
            }
            return $outputFiles;
        }

        /**
         * 🟢 CASO 3: PDF NORMAL CON TEMÁTICA
         */
        $attributeValue = DB::table('attribute_values')->find($tematicaId);
        if (!$attributeValue) throw new \Exception("Temática no encontrada: {$tematicaId}");
        $tematica = strtoupper($attributeValue->value);
        Log::info("🔹 Temática encontrada: {$tematica}");

        $tematicaDb = DB::table('tematicas')->where('name', $attributeValue->value)->first();
        $colores = !empty($tematicaDb->colors) ? json_decode($tematicaDb->colors, true) ?: [] : [];

        // columnas
        $columna = 2;
        foreach (['columna', 'columns', 'cols'] as $f) {
            if (isset($tematicaDb->$f) && is_numeric($tematicaDb->$f)) {
                $columna = (int) $tematicaDb->$f;
                break;
            }
        }

        // 🧹 Limpiar temática: remover acentos y caracteres especiales para la ruta
        $tematicaLimpia = self::limpiarNombreArchivo($tematica);

        // imágenes
        $iconosPath = storage_path("app/pdf/Iconos/Tematicas/{$tematicaLimpia}");
        $imagenes = [];
        if (is_dir($iconosPath)) {
            foreach (scandir($iconosPath) as $f) {
                if (preg_match('/\.(png|jpg|jpeg|svg)$/i', $f)) {
                    $imagenes[] = $iconosPath . DIRECTORY_SEPARATOR . $f;
                }
            }
        }

        $views = $getViews($pdf, $tematica, $urlPdf);

        foreach ($nombres as $nombre) {
            $fontClass = mb_strlen($nombre, 'UTF-8') > 16 ? 'small-text-size' : 'normal-text-size';
            $plantilla = [
                'colores' => $colores,
                'imagen' => $imagenes,
                'fontClass' => $fontClass,
                'columna' => $columna,
                'filas' => 19,
            ];

            $product_order = (object)['name' => $nombre, 'order' => (object)['id_external' => $ventaId]];

            foreach ($views as $i => $view) {
                $filePath = "{$dirPath}/{$ventaId}-{$productOrder->id}-{$productOrder->product->name}-{$tematica}-" . ($i + 1) . ".pdf";
                $renderPdf($view, $plantilla, $product_order, $filePath);
            }
        }

        return $outputFiles;
    }

    /**
     * 🔠 Devuelve el tamaño de fuente según la cantidad de caracteres y la variación.
     */
    private static function getFontSize(string $name, string $typography = ''): string
    {
        $cantCharsName = mb_strlen($name, 'UTF-8');

        if (strtoupper($typography) === 'BOLD') {
            $fontSize = '78px';
            if ($cantCharsName > 5) $fontSize = '70px';
            if ($cantCharsName > 6) $fontSize = '46px';
            if ($cantCharsName > 9) $fontSize = '36px';
            if ($cantCharsName > 11) $fontSize = '32px';
        } else {
            $fontSize = '90px';
            if ($cantCharsName > 5) $fontSize = '82px';
            if ($cantCharsName > 6) $fontSize = '68px';
            if ($cantCharsName > 9) $fontSize = '52px';
            if ($cantCharsName > 11) $fontSize = '46px';
        }

        return $fontSize;
    }

    /**
     * 🧹 Limpia un nombre para uso en rutas de archivos.
     * Remueve acentos, caracteres especiales y espacios múltiples.
     */
    private static function limpiarNombreArchivo(string $nombre): string
    {
        // Tabla de reemplazo de caracteres acentuados
        $acentos = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'Ñ' => 'N', 'ñ' => 'n', 'Ç' => 'C', 'ç' => 'c'
        ];

        // Reemplazar acentos
        $limpio = strtr($nombre, $acentos);

        // Remover caracteres especiales excepto letras, números, espacios y guiones
        $limpio = preg_replace('/[^A-Za-z0-9\s\-]/', '', $limpio);

        // Reemplazar espacios múltiples por uno solo
        $limpio = preg_replace('/\s+/', ' ', $limpio);

        // Trim espacios al inicio y final
        $limpio = trim($limpio);

        return $limpio;
    }
}
