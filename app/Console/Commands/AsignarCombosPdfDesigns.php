<?php

namespace App\Console\Commands;

use App\Models\ProductPdfDesign;
use App\Models\ProductPdfDesignProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Completa product_pdf_design_products.combo_design_id en los vínculos por
 * página que ya existían antes de esa columna (ver
 * database/sql/alter_product_pdf_design_products_add_combo_design.sql).
 *
 * Un diseño "combo" (data.pages[].source) vinculaba cada página por separado
 * sin dejar registro de quién lo hizo: la página en la posición N del combo
 * quedaba como (diseño de origen, page_id de origen, sort_order N) — o
 * (el combo, page_id propio, N) para sus páginas propias. Se reconoce un
 * producto+variante como del combo cuando tiene un vínculo SIN combo para
 * CADA página del combo, en el diseño y la página que corresponden.
 *
 * Por defecto solo muestra lo que haría. Con --apply lo escribe.
 */
class AsignarCombosPdfDesigns extends Command
{
    protected $signature = 'pdf-designs:asignar-combos {--apply : Escribir los cambios (sin esto solo muestra qué haría)}';
    protected $description = 'Completa combo_design_id en los vínculos por página creados por diseños con páginas de otros PDF';

    public function handle(): int
    {
        $combos = ProductPdfDesign::all()->filter(
            fn($d) => collect($d->data['pages'] ?? [])->contains(fn($p) => !empty($p['source']['designId']))
        );

        if ($combos->isEmpty()) {
            $this->info('No hay diseños con páginas de otros PDF.');
            return self::SUCCESS;
        }

        // link_id => [combo_id, ...] — un vínculo que encaja en DOS combos es
        // ambiguo y no se toca.
        $reclamados = [];
        $resumen = [];

        foreach ($combos as $combo) {
            // Qué vínculo espera cada posición del combo.
            $esperado = collect($combo->data['pages'])->values()->map(fn($p, $pos) => [
                'design_id' => (int) ($p['source']['designId'] ?? $combo->id),
                'page_id' => $p['source']['pageId'] ?? ($p['id'] ?? null),
                'pos' => $pos,
            ]);

            $candidatos = ProductPdfDesignProduct::whereNull('combo_design_id')
                ->whereNotNull('page_id')
                ->where(function ($q) use ($esperado) {
                    foreach ($esperado as $e) {
                        $q->orWhere(fn($w) => $w->where('product_pdf_design_id', $e['design_id'])->where('page_id', $e['page_id']));
                    }
                })
                ->get()
                ->groupBy(fn($l) => $l->product_id . '|' . ($l->theme_key ?? 'null'));

            foreach ($candidatos as $clave => $vinculos) {
                $elegidos = [];
                $exacto = true;
                foreach ($esperado as $e) {
                    $deEstaPagina = $vinculos->filter(
                        fn($l) => (int) $l->product_pdf_design_id === $e['design_id'] && $l->page_id === $e['page_id']
                    );
                    // Si hay más de uno, el que tiene el orden de esa posición.
                    $link = $deEstaPagina->firstWhere('sort_order', $e['pos']) ?? $deEstaPagina->first();
                    if (!$link) {
                        $elegidos = null;
                        break;
                    }
                    $exacto = $exacto && (int) $link->sort_order === $e['pos'];
                    $elegidos[] = $link->id;
                }
                if (!$elegidos) {
                    continue;
                }
                $grupo = ['combo' => $combo->id, 'exacto' => $exacto, 'paginas' => $esperado->count()];
                foreach ($elegidos as $linkId) {
                    $reclamados[$linkId][$combo->id] = $grupo;
                }
                $resumen[$combo->id][$clave] = $elegidos;
            }
        }

        // Un vínculo que encaja en 2+ combos (ej. un combo "jardín" que usa
        // un subconjunto de las páginas del combo "primaria"): gana el combo
        // donde el sort_order coincide con su posición; si sigue empatado, el
        // de más páginas. Si aun así hay empate, es ambiguo y no se toca.
        $ganador = [];
        foreach ($reclamados as $linkId => $porCombo) {
            $opciones = collect($porCombo);
            if ($opciones->count() > 1 && $opciones->where('exacto', true)->isNotEmpty()) {
                $opciones = $opciones->where('exacto', true);
            }
            $max = $opciones->max('paginas');
            $opciones = $opciones->where('paginas', $max);
            $ganador[$linkId] = $opciones->count() === 1 ? $opciones->first()['combo'] : null;
        }

        $asignar = [];
        $filas = [];
        foreach ($resumen as $comboId => $grupos) {
            foreach ($grupos as $clave => $linkIds) {
                [$productId, $themeKey] = explode('|', $clave);
                $ganados = collect($linkIds)->every(fn($id) => $ganador[$id] === $comboId);
                $empatado = collect($linkIds)->contains(fn($id) => $ganador[$id] === null);
                $accion = $ganados
                    ? 'asignar'
                    : ($empatado ? 'AMBIGUO (empate con otro combo) — no se toca' : 'no: esos vínculos son de otro combo');
                $filas[] = [
                    $comboId,
                    $combos->firstWhere('id', $comboId)->name,
                    $productId,
                    $themeKey,
                    implode(',', $linkIds),
                    $accion,
                ];
                if ($ganados) {
                    foreach ($linkIds as $linkId) {
                        $asignar[$linkId] = $comboId;
                    }
                }
            }
        }
        $ambiguos = array_filter($ganador, fn($c) => $c === null);

        if (!$filas) {
            $this->info('No se encontraron vínculos por página para asignar.');
            return self::SUCCESS;
        }

        $this->table(['Combo', 'Nombre', 'Producto', 'Variante', 'Vínculos', 'Acción'], $filas);

        if (!$this->option('apply')) {
            $this->warn('Simulación: no se escribió nada. Correr con --apply para asignar ' . count($asignar) . ' vínculo(s).');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($asignar) {
            foreach ($asignar as $linkId => $comboId) {
                ProductPdfDesignProduct::where('id', $linkId)->whereNull('combo_design_id')->update(['combo_design_id' => $comboId]);
            }
        });

        $this->info('Asignados ' . count($asignar) . ' vínculo(s).');
        if ($ambiguos) {
            $this->warn(count($ambiguos) . ' vínculo(s) ambiguos quedaron sin combo: revisarlos a mano.');
        }

        return self::SUCCESS;
    }
}
