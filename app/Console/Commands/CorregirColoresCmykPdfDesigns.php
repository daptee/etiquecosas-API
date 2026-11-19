<?php

namespace App\Console\Commands;

use App\Models\ProductPdf;
use App\Models\ProductPdfDesign;
use App\Models\ProductPdfDesignProduct;
use App\Services\PdfDesignImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diseños importados desde un producto (ProductPdfDesignController::
 * importFromProduct) ANTES de que el importador guardara el color de los
 * textos en CMYK: las vistas viejas pintan los nombres con `color:
 * cmyk(...)` (product_pdf.data.tematicas[].color-range) y el import lo
 * pasaba a hex con la conversión directa, mucho más saturada que lo que
 * muestra el PDF original (verde agua 0.53,0,0.47,0 → #78FF87).
 *
 * Para cada diseño vinculado a un producto con product_pdf, busca su temática
 * por el nombre del diseño ("<producto> - <temática> (importado)") y, en los
 * textos dinámicos cuyo hex es EXACTAMENTE la conversión directa de uno de
 * los CMYK de esa temática, vuelve a poner el CMYK. Un color cambiado a mano
 * en el editor no coincide y no se toca.
 *
 * Por defecto solo muestra lo que haría. Con --apply lo escribe.
 */
class CorregirColoresCmykPdfDesigns extends Command
{
    protected $signature = 'pdf-designs:corregir-colores-cmyk {--design=* : Solo estos diseños (id)} {--apply : Escribir los cambios (sin esto solo muestra qué haría)}';
    protected $description = 'Vuelve a CMYK el color de los textos de diseños importados de productos con PDF viejo';

    public function handle(): int
    {
        $links = ProductPdfDesignProduct::query()
            ->when($this->option('design'), fn($q, $ids) => $q->whereIn('product_pdf_design_id', $ids))
            ->get(['product_pdf_design_id', 'product_id'])
            ->unique(fn($l) => $l->product_pdf_design_id . '|' . $l->product_id);

        $filas = [];
        $cambios = [];

        foreach ($links->groupBy('product_pdf_design_id') as $designId => $linksDelDiseno) {
            $design = ProductPdfDesign::find($designId);
            if (!$design || empty($design->data['pages'])) {
                continue;
            }

            $tematica = null;
            foreach ($linksDelDiseno as $link) {
                $tematicas = ProductPdf::where('product_id', $link->product_id)->first()?->data['tematicas'] ?? [];
                // Sin espacios: "verde lima , azul" (nombre del diseño) vs "verde lima, azul" (temática).
                $normalizar = fn(string $s) => preg_replace('/\s+/u', '', mb_strtolower($s));
                $tematica = collect($tematicas)
                    ->filter(fn($t) => !empty($t['name']) && str_contains($normalizar($design->name), $normalizar('-' . $t['name'] . '(importado)')))
                    ->sortByDesc(fn($t) => mb_strlen($t['name'])) // "Blanco y Negro" antes que "Negro"
                    ->first();
                if ($tematica) {
                    break;
                }
            }
            if (!$tematica || empty($tematica['color-range'])) {
                continue;
            }

            // hex de la conversión directa => CMYK original.
            $porHex = [];
            foreach ($tematica['color-range'] as $cmyk) {
                $partes = array_map('floatval', explode(',', (string) $cmyk));
                if (count($partes) === 4) {
                    $porHex[self::cmykToHex(...$partes)] = implode(',', array_map([self::class, 'trimNum'], $partes));
                }
            }

            $data = $design->data;
            $cambiados = 0;
            foreach ($data['pages'] as $p => $page) {
                $sigsAntes = [];
                foreach ($page['elements'] ?? [] as $i => $el) {
                    if (($el['type'] ?? null) !== 'text' || ($el['value_mode'] ?? null) !== 'dynamic') {
                        continue;
                    }
                    if (($el['color']['mode'] ?? 'hex') !== 'hex') {
                        continue;
                    }
                    $cmyk = $porHex[strtoupper($el['color']['value'] ?? '')] ?? null;
                    if ($cmyk === null) {
                        continue;
                    }
                    if (isset($el['id'])) {
                        $sigsAntes[$el['id']] = PdfDesignImportService::elementSignature($el);
                    }
                    $data['pages'][$p]['elements'][$i]['color'] = array_merge($el['color'], ['mode' => 'cmyk', 'value' => $cmyk]);
                    $cambiados++;
                }

                // Mismo cuidado que ProductPdfDesignController::withIconNames():
                // el sig de una celda de grilla incluye el color — se recalcula
                // solo donde coincidía (celda no editada a mano).
                if ($sigsAntes && !empty($page['grids'])) {
                    $porId = collect($data['pages'][$p]['elements'])->keyBy('id');
                    foreach ($data['pages'][$p]['grids'] as $g => $grid) {
                        foreach ($grid['members'] ?? [] as $k => $member) {
                            foreach ((array) $member as $m => $entry) {
                                $id = $entry['id'] ?? null;
                                if ($id !== null && isset($sigsAntes[$id]) && ($entry['sig'] ?? null) === $sigsAntes[$id]) {
                                    $data['pages'][$p]['grids'][$g]['members'][$k][$m]['sig'] = PdfDesignImportService::elementSignature($porId[$id]);
                                }
                            }
                        }
                    }
                }
            }

            if ($cambiados) {
                $filas[] = [$design->id, $design->name, $tematica['name'], $cambiados, implode(' | ', array_map(fn($h, $c) => "{$h} → cmyk({$c})", array_keys($porHex), $porHex))];
                $cambios[$design->id] = $data;
            }
        }

        if (!$filas) {
            $this->info('No hay textos para corregir.');
            return self::SUCCESS;
        }

        $this->table(['Diseño', 'Nombre', 'Temática', 'Textos', 'Colores'], $filas);

        if (!$this->option('apply')) {
            $this->warn('Simulación: no se escribió nada. Correr con --apply para corregir ' . count($cambios) . ' diseño(s).');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($cambios) {
            foreach ($cambios as $id => $data) {
                ProductPdfDesign::find($id)->update(['data' => $data]);
            }
        });
        $this->info('Corregidos ' . count($cambios) . ' diseño(s).');

        return self::SUCCESS;
    }

    // Misma conversión que PdfContentStreamParser::cmykToHex() (la que usaba el import).
    private static function cmykToHex(float $c, float $m, float $y, float $k): string
    {
        $clamp = fn($v) => max(0, min(255, (int) round(255 * (1 - $v) * (1 - $k))));
        return sprintf('#%02X%02X%02X', $clamp($c), $clamp($m), $clamp($y));
    }

    // Mismo formato que PdfContentStreamParser::trimNum().
    private static function trimNum(float $v): string
    {
        return rtrim(rtrim(number_format(max(0, min(1, $v)), 6, '.', ''), '0'), '.') ?: '0';
    }
}
