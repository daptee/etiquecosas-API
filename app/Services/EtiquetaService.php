<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EtiquetaService
{
    /**
     * Generar etiquetas PDF
     *
     * @param int   $ventaId
     * @param array $nombres
     * @return array Rutas de los PDFs generados
     */
    public static function generarEtiquetas(int $ventaId, $tematicaId, array $nombres, $productOrder, $pdf, $customColor, $customIcon): array
    {
        $outputFiles = [];
        $hoy = date('Y-m-d');
        $dirPath = storage_path("app/pdf/planchas/{$hoy}/{$ventaId}");
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        Log::info($customColor);
        Log::info($customIcon);

        /**
         * 🟣 CASO 1: PDF PERSONALIZABLE (no hay temática)
         */
        if ($customIcon && $customColor) {
            Log::info("🟣 Generando PDF PERSONALIZABLE");

            $customIconPath = public_path($customIcon);

            // 📄 Mapeo de vistas igual que en las temáticas normales
            $nameToView = [
                'Etiquetas maxi, verticales, super-maxi, super-mini' => "tematica/principal/PERSONALIZABLE",
                'Etiquetas vinilo' => "tematica/vinilo/PERSONALIZABLE",
                'Etiquetas super-mini' => "tematica/super-mini/PERSONALIZABLE",
                'Etiquetas super-maxi' => "tematica/super-maxi/PERSONALIZABLE",
                'Etiquetas maxi' => "tematica/maxi/PERSONALIZABLE",
                'Etiquetas spot and maxi' => "tematica/spot-and-maxi/PERSONALIZABLE",
                'Etiquetas maxi and super maxi and super mini' => "tematica/maxi-and-super-maxi-and-super-mini/PERSONALIZABLE",
                'Etiquetas planchables' => "tematica/planchable/PERSONALIZABLE",
            ];

            $views = [];
            if ($pdf) {
                foreach ($pdf as $name) {
                    if (isset($nameToView[$name])) {
                        $views[] = $nameToView[$name];
                    }
                }
            } else {
                $views = [
                    "tematica/principal/PERSONALIZABLE",
                    "tematica/vinilo/PERSONALIZABLE",
                    "tematica/super-mini/PERSONALIZABLE",
                ];
            }

            foreach ($nombres as $i => $nombre) {
                $fontClass = mb_strlen($nombre, 'UTF-8') > 16
                    ? 'small-text-size'
                    : 'normal-text-size';

                $plantilla = [
                    'colores' => $customColor,
                    'imagen' => $customIconPath,
                    'fontClass' => $fontClass,
                    'columna' => 2,
                    'filas' => 19,
                ];

                Log::info($plantilla);

                $product_order = (object) [
                    'name' => $nombre,
                    'order' => (object) ['id_external' => $ventaId],
                ];

                foreach ($views as $vKey => $view) {
                    $vKey += 1;
                    try {
                        $pdf = Pdf::loadView($view, compact('plantilla', 'product_order'))
                            ->setPaper('a4', 'portrait');

                        $dompdf = $pdf->getDomPDF();
                        $dompdf->getOptions()->setFontDir(public_path('fonts'));
                        $dompdf->getOptions()->setFontCache(storage_path('fonts_cache'));

                        $filePath = "{$dirPath}/{$ventaId}-{$productOrder->product->name}-PERSONALIZABLE-{$vKey}.pdf";
                        $pdf->save($filePath);

                        $outputFiles[] = $filePath;

                        Log::info("✅ PDF PERSONALIZABLE generado", ['path' => $filePath]);
                    } catch (\Throwable $e) {
                        Log::error("❌ Error generando PDF PERSONALIZABLE", [
                            'nombre' => $nombre,
                            'view' => $view,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return $outputFiles; // 🚪 Salimos antes de procesar temática
        }

        /**
         * 🟢 CASO 2: PDF NORMAL CON TEMÁTICA
         */
        $attributeValue = DB::table('attribute_values')->where('id', $tematicaId)->first();
        if (!$attributeValue) {
            throw new \Exception("Temática no encontrada: {$tematicaId}");
        }
        $tematica = strtoupper($attributeValue->value); // normalizamos may/min

        Log::info("🔹 Temática encontrada: {$tematica}");

        // 1.a Colores
        $colores = [];
        $tematicaDb = DB::table('tematicas')->where('name', $attributeValue->value)->first();
        if (!empty($tematicaDb->colors)) {
            $colores = json_decode($tematicaDb->colors, true) ?: [];
        }

        // 1.b Columnas
        $columna = 2;
        foreach (['columna', 'columns', 'cols'] as $field) {
            if (isset($tematicaDb->$field) && is_numeric($tematicaDb->$field)) {
                $columna = (int) $tematicaDb->$field;
                break;
            }
        }

        // 2. Cargar imágenes
        $iconosPath = storage_path("app/pdf/Iconos/Tematicas/{$tematica}");
        $imagenes = [];
        if (is_dir($iconosPath)) {
            foreach (scandir($iconosPath) as $file) {
                if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'svg'])) {
                    $imagenes[] = $iconosPath . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        // 3. Vistas de PDF
        Log::info($pdf);

        if ($pdf) {
            $nameToView = [
                'Etiquetas maxi, verticales, super-maxi, super-mini' => "tematica/principal/{$tematica}",
                'Etiquetas vinilo' => "tematica/vinilo/{$tematica}",
                'Etiquetas super-mini' => "tematica/super-mini/{$tematica}",
                'Etiquetas super-maxi' => "tematica/super-maxi/{$tematica}"
            ];

            // Generar solo las vistas que correspondan a los nombres recibidos
            $views = [];

            foreach ($pdf as $name) {
                if (isset($nameToView[$name])) {
                    $views[] = $nameToView[$name];
                }
            }
        } else {
            $views = [
                "tematica/principal/{$tematica}",
                "tematica/vinilo/{$tematica}",
                "tematica/super-mini/{$tematica}",
            ];
        }

        foreach ($nombres as $i => $nombre) {
            $fontClass = mb_strlen($nombre, 'UTF-8') > 16
                ? 'small-text-size'
                : 'normal-text-size';

            Log::info($fontClass);
            Log::info($nombre);

            $plantilla = [
                'colores' => $colores,
                'imagen' => $imagenes,
                'fontClass' => $fontClass,
                'columna' => $columna,
                'filas' => 19,
            ];

            $product_order = (object) [
                'name' => $nombre,
                'order' => (object) ['id_external' => $ventaId],
            ];

            // empezar desde 1 para nombres legibles
            foreach ($views as $vKey => $view) {
                $vKey += 1;
                try {
                    Log::info(json_encode($product_order));
                    $pdf = Pdf::loadView($view, compact('plantilla', 'product_order'))
                        ->setPaper('a4', 'portrait');

                    $dompdf = $pdf->getDomPDF();
                    $dompdf->getOptions()->setFontDir(public_path('fonts'));
                    $dompdf->getOptions()->setFontCache(storage_path('fonts_cache'));

                    $filePath = "{$dirPath}/{$ventaId}-{$productOrder->product->name}-{$tematica}-{$vKey}.pdf";
                    $pdf->save($filePath);

                    $outputFiles[] = $filePath;

                    Log::info("✅ PDF generado", ['path' => $filePath]);
                } catch (\Throwable $e) {
                    Log::error("❌ Error generando PDF", [
                        'nombre' => $nombre,
                        'view' => $view,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $outputFiles;
    }
}
