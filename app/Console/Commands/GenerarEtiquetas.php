<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class GenerarEtiquetas extends Command
{
    protected $signature = 'etiquetas:generar {tematica=AIRE}';
    protected $description = 'Generar etiquetas en PDF desde Blade con colores e iconos';

    public function handle()
    {
        $tematica = $this->argument('tematica');

        // 1. Cargar colores desde la tabla tematicas
        $tematicaDb = DB::table('tematicas')
            ->where('name', $tematica)
            ->first();

        $colores = [];
        if ($tematicaDb && !empty($tematicaDb->colors)) {
            $colores = json_decode($tematicaDb->colors, true);
        }

        \Log::info('Colores cargados correctamente desde DB', $colores);
        \Log::info('Cantidad de colores', ['total' => count($colores)]);

        // 2. Cargar imágenes de la temática
        $iconosPath = storage_path("app/pdf/Iconos/Tematicas/{$tematica}");
        $imagenes = [];
        if (is_dir($iconosPath)) {
            foreach (scandir($iconosPath) as $file) {
                if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['png','jpg','jpeg','svg'])) {
                    $imagenes[] = $iconosPath . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        // 3. Fake producto de ejemplo
        $product_order = (object)[
            'name' => 'NAHUEL ESTEBAN CARRIZO',
            'order' => (object)[
                'id_external' => '12345'
            ]
        ];

        // 3b. Validar longitud del nombre y asignar clase de fuente
        $cantCharsName = mb_strlen($product_order->name, 'UTF-8');
        $fontClass = 'normal-text-size';
        if ($cantCharsName > 16) {
            $fontClass = 'small-text-size';
        }

        // 4. Preparar plantilla
        $plantilla = [
            'colores'   => $colores,
            'imagen'    => $imagenes,
            'fontClass' => $fontClass,
        ];

        // 5. Renderizar PDF
        // el blade tiene el nombre de la temática
        $pdf = Pdf::loadView('tematica/' . $tematica, compact('plantilla', 'product_order'))
            ->setPaper('a4', 'portrait');

        // 6. Registrar fuentes locales
        $dompdf = $pdf->getDomPDF();
        $dompdf->getOptions()->setFontDir(public_path('fonts')); // Solo un path
        $dompdf->getOptions()->setFontCache(storage_path('fonts_cache')); // Cache de fuentes

        // 7. Guardar PDF
        $outputPath = storage_path("app/pdf/etiquetas-{$tematica}.pdf");
        $pdf->save($outputPath);

        $this->info("✅ PDF generado: {$outputPath}");
    }
}
