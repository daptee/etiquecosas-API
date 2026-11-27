<?php

namespace App\Console\Commands;

use App\Models\ProductPdfDesign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repara un `font_size_rules` (tope de tamaño de letra según largo de texto,
 * ver sanitizeElements()) que quedó con un valor absurdo en la regla
 * "catch-all" (max_chars: null) de algún elemento, mientras TODOS los demás
 * elementos del mismo diseño con el mismo `content` (ej. una grilla entera de
 * "{{customer_name}}") usan otro valor — esto hace que esa etiqueta puntual
 * salga con el texto gigante, desbordado y tapado por las etiquetas vecinas
 * (ver el diseño 239, donde una columna entera de la grilla tenía
 * font_size_px: 112 en vez de 12 — probablemente un typo al tocar esa
 * variante a mano, o una propagación de un ajuste manual al duplicar la fila).
 *
 * Agrupa por `content` + tamaño del elemento (width_cm/height_cm) y NO solo
 * por `content`, a propósito: un diseño puede combinar a conciencia dos
 * tamaños de etiqueta con el mismo campo dinámico, cada uno con su propio
 * tamaño de letra (ver diseño 175, "2 tamaños - Maxi - supermini" — ahí el
 * grupo chico de valor distinto es legítimo, no corrupción). Agrupando
 * también por tamaño de caja, esas dos variantes quedan en grupos separados
 * y no se tocan entre sí.
 *
 * Por cada grupo de 3+ elementos de texto con el mismo `content` en un mismo
 * diseño: si un valor de font_size_px (catch-all) es ampliamente mayoritario
 * (>= 70% del grupo) y algún otro elemento del grupo se aparta mucho de ese
 * valor (más del 50% para arriba o para abajo), lo marca como anómalo y lo
 * normaliza al valor mayoritario.
 *
 * Por defecto solo muestra lo que haría. Con --apply lo escribe.
 */
class CorregirFontSizeRulesAnomalos extends Command
{
    protected $signature = 'pdf-designs:corregir-font-size-rules-anomalos {--design=* : Solo estos diseños (id)} {--apply : Escribir los cambios (sin esto solo muestra qué haría)}';
    protected $description = 'Normaliza un font_size_rules (catch-all) que quedó muy distinto al de sus hermanos (mismo content, mismo diseño)';

    public function handle(): int
    {
        $designs = ProductPdfDesign::query()
            ->when($this->option('design'), fn($q, $ids) => $q->whereIn('id', $ids))
            ->get();

        $filas = [];
        $cambios = [];

        foreach ($designs as $design) {
            if (empty($design->data['pages']) || !is_array($design->data['pages'])) {
                continue;
            }

            $data = $design->data;
            $totalCorregidos = 0;

            foreach ($data['pages'] as $pageIdx => $page) {
                if (empty($page['elements']) || !is_array($page['elements'])) {
                    continue;
                }

                $elements = $page['elements'];

                // Agrupar índices por content + tamaño de caja (ver nota de
                // clase arriba), solo elementos de texto con font_size_rules.
                $grupos = [];
                foreach ($elements as $idx => $el) {
                    if (!is_array($el) || ($el['type'] ?? null) !== 'text' || empty($el['font_size_rules'])) {
                        continue;
                    }
                    $content = (string) ($el['content'] ?? '');
                    $clave = $content . '|' . round((float) ($el['width_cm'] ?? 0), 2) . 'x' . round((float) ($el['height_cm'] ?? 0), 2);
                    $grupos[$clave][] = $idx;
                }

                foreach ($grupos as $clave => $idxs) {
                    $content = explode('|', $clave, 2)[0];
                    if (count($idxs) < 3) {
                        continue;
                    }

                    $catchAllByIdx = [];
                    foreach ($idxs as $idx) {
                        foreach ($elements[$idx]['font_size_rules'] as $rule) {
                            if (is_array($rule) && array_key_exists('max_chars', $rule) && $rule['max_chars'] === null) {
                                $catchAllByIdx[$idx] = $rule['font_size_px'] ?? null;
                            }
                        }
                    }
                    if (count($catchAllByIdx) < 3) {
                        continue;
                    }

                    $conteo = array_count_values(array_map('strval', $catchAllByIdx));
                    arsort($conteo);
                    $valorMayoritario = (float) array_key_first($conteo);
                    $cantidadMayoritaria = reset($conteo);
                    if ($cantidadMayoritaria / count($catchAllByIdx) < 0.7 || $valorMayoritario <= 0) {
                        continue;
                    }

                    foreach ($catchAllByIdx as $idx => $valor) {
                        $valor = (float) $valor;
                        if ($valor == $valorMayoritario) {
                            continue;
                        }
                        if ($valor < $valorMayoritario * 1.5 && $valor > $valorMayoritario * 0.5) {
                            continue; // distinto pero no descabellado, no se toca
                        }

                        foreach ($elements[$idx]['font_size_rules'] as $ruleIdx => $rule) {
                            if (is_array($rule) && array_key_exists('max_chars', $rule) && $rule['max_chars'] === null) {
                                $elements[$idx]['font_size_rules'][$ruleIdx]['font_size_px'] = (int) $valorMayoritario;
                            }
                        }
                        $totalCorregidos++;
                        $filas[] = [$design->id, $design->name, $elements[$idx]['id'] ?? $idx, mb_strimwidth($content, 0, 30, '…'), (int) $valor, (int) $valorMayoritario];
                    }
                }

                $data['pages'][$pageIdx]['elements'] = $elements;
            }

            if ($totalCorregidos > 0) {
                $cambios[$design->id] = $data;
            }
        }

        if (!$filas) {
            $this->info('No hay elementos con font_size_rules anómalo.');
            return self::SUCCESS;
        }

        $this->table(['Diseño', 'Nombre', 'Elemento', 'Content', 'Valor anómalo', 'Corregido a'], $filas);

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
}
