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
     * @param int   $tematicaId
     * @param array $nombres
     * @return array Rutas de los PDFs generados
     */
    public static function generarEtiquetas(int $ventaId, int $tematicaId, array $nombres, $productOrder): array
    {
        Log::info("🔹 Iniciando generación de etiquetas", [
            'ventaId' => $ventaId,
            'tematicaId' => $tematicaId,
            'nombres_count' => count($nombres),
        ]);

        // 1. Buscar temática
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
        $views = [
            "tematica/pdf-01/{$tematica}",
            "tematica/pdf-02/{$tematica}",
            "tematica/pdf-03/{$tematica}",
        ];

        // 4. Generar PDFs individuales (uno por nombre)
        $outputFiles = [];
        $hoy = date('Y-m-d');
        $dirPath = storage_path("app/pdf/planchas/{$hoy}/{$ventaId}");
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        foreach ($nombres as $i => $nombre) {
            $fontClass = mb_strlen($nombre, 'UTF-8') > 16
                ? 'small-text-size'
                : 'normal-text-size';

            Log::info($fontClass);
            Log::info($nombre);

            $plantilla = [
                'colores'   => $colores,
                'imagen'    => $imagenes,
                'fontClass' => $fontClass,
                'columna'   => $columna,
                'filas'     => 19,
            ];

            $product_order = (object) [
                'name'  => $nombre,
                'order' => (object) ['id_external' => $ventaId],
            ];

            // empezar desde 1 para nombres legibles
            foreach ($views as $vKey => $view) {
                $vKey += 1;                
                try {
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
