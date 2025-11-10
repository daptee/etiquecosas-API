<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Fpdi;

class GenerarEtiquetas extends Command
{
    protected $signature = 'etiquetas:generar {tematica=AIRE}';
    protected $description = 'Generar etiquetas en PDF desde Blade con colores e iconos';

    public function handle()
    {
        $tematica = $this->argument('tematica');

        // 1. Buscar la temática en DB
        $tematicaDb = DB::table('tematicas')
            ->where('name', $tematica)
            ->first();

        // 1.a Colores
        $colores = [];
        if ($tematicaDb && !empty($tematicaDb->colors)) {
            $colores = json_decode($tematicaDb->colors, true) ?: [];
        }
        \Log::info('Colores cargados', [
            'raw'   => $tematicaDb->colors ?? null,
            'total' => count($colores),
        ]);

        // 1.b Columnas
        $columna = 2;
        if ($tematicaDb) {
            foreach (['columna', 'columns', 'cols'] as $field) {
                if (isset($tematicaDb->$field) && is_numeric($tematicaDb->$field)) {
                    $columna = (int) $tematicaDb->$field;
                    break;
                }
            }
        }
        \Log::info('Columnas a utilizar', ['columna' => $columna]);

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
        \Log::info('Imágenes encontradas', ['count' => count($imagenes)]);

        // 3. Producto fake de ejemplo
        $product_order = (object) [
            'name' => 'NAHUEL ESTEBAN CARRIZO',
            'order' => (object) [
                'id_external' => '12345',
            ],
        ];

        // 3.b Clase de fuente según largo del nombre
        $fontClass = mb_strlen($product_order->name, 'UTF-8') > 16
            ? 'small-text-size'
            : 'normal-text-size';

        // 4. Plantilla que recibe el blade
        $plantilla = [
            'colores'   => $colores,
            'imagen'    => $imagenes,
            'fontClass' => $fontClass,
            'columna'   => $columna,
            'filas'     => 19,
        ];
        \Log::info('Plantilla final', [
            'colores'  => count($plantilla['colores']),
            'imagenes' => count($plantilla['imagen']),
            'columna'  => $plantilla['columna'],
            'font'     => $plantilla['fontClass'],
        ]);

        // 5. Vistas de PDF
        $views = [
            'tematica/pdf-01/' . $tematica,
            'tematica/pdf-02/' . $tematica,
            'tematica/pdf-03/' . $tematica,
        ];

        foreach ($views as $view) {
            if (!view()->exists($view)) {
                $this->error("Vista no encontrada: {$view}");
                \Log::error("Vista no encontrada", ['view' => $view]);
                return;
            }
        }

        // 6. Generar PDFs individuales
        $pdfs = [];
        foreach ($views as $index => $view) {
            $pdfs[$index] = Pdf::loadView($view, compact('plantilla', 'product_order'))
                ->setPaper('a4', 'portrait');

            // Configuración de fuentes (ubicadas en public/fonts)
            $dompdf = $pdfs[$index]->getDomPDF();
            $dompdf->getOptions()->setFontDir(public_path('fonts'));
            $dompdf->getOptions()->setFontCache(storage_path('fonts_cache'));
        }

        // 7. Guardar PDFs individuales y mergear
        $tmpFiles = [];
        // fecha de hoy
        $hoy = date('Y-m-d');
        foreach ($pdfs as $i => $pdf) {
            // Crear carpeta si no existe
            $dirPath = storage_path("app/pdf/{$hoy}/{$product_order->order->id_external}");
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            $tmpPath = storage_path("app/pdf/{$hoy}/{$product_order->order->id_external}/tmp{$i}.pdf");
            $pdf->save($tmpPath);
            $tmpFiles[] = $tmpPath;
        }

        $outputPath = storage_path("app/pdf/etiquetas-{$tematica}.pdf");
        try {
            $fpdi = new Fpdi();
            foreach ($tmpFiles as $file) {
                $pageCount = $fpdi->setSourceFile($file);
                for ($page = 1; $page <= $pageCount; $page++) {
                    $tplId = $fpdi->importPage($page);
                    $size = $fpdi->getTemplateSize($tplId);
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($tplId);
                }
            }
            $fpdi->Output($outputPath, 'F');
        } catch (\Throwable $e) {
            \Log::error('Error al mergear PDFs: ' . $e->getMessage());
            $this->info("⚠️ Se generaron PDFs parciales.");
            return;
        }

        $this->info("✅ PDF generado: {$outputPath}");
    }
}
