<?php

namespace App\Console\Commands;

use App\Models\ProductPdfDesign;
use App\Models\ProductPdfDesignProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasa los productos vinculados a un diseño "combo" (data.pages[].source,
 * páginas prestadas de otros diseños) al combo mismo.
 *
 * El editor vinculaba un combo página por página en los diseños de ORIGEN:
 * la página N del combo quedaba como (diseño de origen, page_id de origen,
 * sort_order N) — o (el combo, page_id propio, N) para sus páginas propias —
 * y el combo no tenía ningún vínculo propio. Este comando reconoce esos
 * grupos (un vínculo por CADA página del combo, para el mismo
 * producto+variante), crea UN vínculo al combo entero (page_id null) y borra
 * los vínculos por página. Al comprar, EtiquetaService::
 * generarEtiquetasDesdeDesign() completa las páginas prestadas y usa el
 * pages_output_mode del combo.
 *
 * Por defecto solo muestra lo que haría. Con --apply lo escribe.
 */
class VincularCombosPdfDesigns extends Command
{
    protected $signature = 'pdf-designs:vincular-combos {--apply : Escribir los cambios (sin esto solo muestra qué haría)}';
    protected $description = 'Pasa los vínculos por página de los diseños con páginas prestadas a un único vínculo al diseño combo';

    public function handle(): int
    {
        $combos = ProductPdfDesign::all()->filter(
            fn($d) => collect($d->data['pages'] ?? [])->contains(fn($p) => !empty($p['source']['designId']))
        );

        if ($combos->isEmpty()) {
            $this->info('No hay diseños con páginas de otros PDF.');
            return self::SUCCESS;
        }

        // link_id => [combo_id => grupo]: un vínculo que encaja en DOS combos
        // se resuelve abajo.
        $reclamados = [];
        $resumen = [];

        foreach ($combos as $combo) {
            // Qué vínculo espera cada posición del combo.
            $esperado = collect($combo->data['pages'])->values()->map(fn($p, $pos) => [
                'design_id' => (int) ($p['source']['designId'] ?? $combo->id),
                'page_id' => $p['source']['pageId'] ?? ($p['id'] ?? null),
                'pos' => $pos,
            ]);

            $candidatos = ProductPdfDesignProduct::whereNotNull('page_id')
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

        $convertir = [];
        $filas = [];
        foreach ($resumen as $comboId => $grupos) {
            foreach ($grupos as $clave => $linkIds) {
                [$productId, $themeKey] = explode('|', $clave);
                $ganados = collect($linkIds)->every(fn($id) => $ganador[$id] === $comboId);
                $empatado = collect($linkIds)->contains(fn($id) => $ganador[$id] === null);
                $accion = $ganados
                    ? 'pasar al combo'
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
                    $convertir[] = [
                        'combo_id' => $comboId,
                        'product_id' => (int) $productId,
                        'theme_key' => $themeKey === 'null' ? null : (int) $themeKey,
                        'link_ids' => $linkIds,
                    ];
                }
            }
        }
        $ambiguos = array_filter($ganador, fn($c) => $c === null);

        if (!$filas) {
            $this->info('No se encontraron vínculos por página de combos.');
            return self::SUCCESS;
        }

        $this->table(['Combo', 'Nombre', 'Producto', 'Variante', 'Vínculos por página', 'Acción'], $filas);

        if (!$this->option('apply')) {
            $this->warn('Simulación: no se escribió nada. Correr con --apply para pasar ' . count($convertir) . ' producto/variante(s) a su combo.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($convertir) {
            foreach ($convertir as $c) {
                $sortOrder = (int) ProductPdfDesignProduct::whereIn('id', $c['link_ids'])->min('sort_order');
                ProductPdfDesignProduct::firstOrCreate(
                    [
                        'product_pdf_design_id' => $c['combo_id'],
                        'product_id' => $c['product_id'],
                        'theme_key' => $c['theme_key'],
                        'page_id' => null,
                        'label_element_id' => null,
                    ],
                    ['sort_order' => $sortOrder]
                );
                ProductPdfDesignProduct::whereIn('id', $c['link_ids'])->delete();
            }
        });

        $this->info('Pasados ' . count($convertir) . ' producto/variante(s) a su combo.');
        if ($ambiguos) {
            $this->warn(count($ambiguos) . ' vínculo(s) ambiguos quedaron como estaban: revisarlos a mano.');
        }

        return self::SUCCESS;
    }
}
