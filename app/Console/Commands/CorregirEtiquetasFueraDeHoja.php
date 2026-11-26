<?php

namespace App\Console\Commands;

use App\Models\ProductPdfDesign;
use App\Services\PdfDesignSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repara diseños que quedaron con elementos fuera de los límites físicos de
 * la hoja (ver PdfDesignSanitizer::validateElementsWithinSheet() y
 * PDF_LIMITES_DE_HOJA.md) — esto ya no puede volver a pasar al guardar desde
 * el editor (ProductPdfDesignController::store()/update() ahora lo rechaza
 * con un 422), pero un diseño guardado ANTES de ese fix (ej. el 111, con
 * filas de una grilla por debajo del borde de la hoja) se queda corrupto
 * hasta que alguien lo corrija — este comando hace esa limpieza.
 *
 * Por cada elemento fuera de la hoja, borra la CELDA ENTERA de su grilla si
 * la tiene (todos los elementos de esa misma posición cols/rows — fondo +
 * ícono + texto suelen desbordar juntos, nunca uno solo), no solo el
 * elemento puntual que disparó el error — así no quedan íconos/textos
 * huérfanos de un fondo ya borrado o viceversa. layout_groups que se quedan
 * con un solo miembro (o ninguno) se borran, igual que ya hace el editor del
 * front con una "Relación" rota.
 *
 * Por defecto solo muestra lo que haría. Con --apply lo escribe.
 */
class CorregirEtiquetasFueraDeHoja extends Command
{
    protected $signature = 'pdf-designs:corregir-etiquetas-fuera-de-hoja {--design=* : Solo estos diseños (id)} {--apply : Escribir los cambios (sin esto solo muestra qué haría)}';
    protected $description = 'Borra los elementos (y celdas de grilla enteras) que quedaron fuera de los límites de la hoja en diseños guardados antes de que esto se empezara a validar';

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
            $totalElementosBorrados = 0;
            $totalCeldasBorradas = 0;

            foreach ($data['pages'] as $pageIdx => $page) {
                if (empty($page['sheet']) || empty($page['elements']) || !is_array($page['elements'])) {
                    continue;
                }

                $elErrors = PdfDesignSanitizer::validateElementsWithinSheet($page['elements'], $page['sheet']);
                if (!$elErrors) {
                    continue;
                }

                $idsABorrar = [];
                foreach (array_keys($elErrors) as $elIdx) {
                    $id = $page['elements'][$elIdx]['id'] ?? null;
                    if ($id !== null) {
                        $idsABorrar[(string) $id] = true;
                    }
                }

                // Ampliar a la celda de grilla entera: si CUALQUIER elemento de
                // un mismo slot (offset col,row) está marcado, se borran TODOS
                // los de ese slot (y, si el slot es la plantilla de la grilla,
                // la grilla entera no puede seguir existiendo sin ella).
                $gridsABorrarEnteros = [];
                foreach ($page['grids'] ?? [] as $gridIdx => $grid) {
                    $templateIds = array_map('strval', $grid['template'] ?? []);
                    if (array_intersect($templateIds, array_keys($idsABorrar))) {
                        $gridsABorrarEnteros[$gridIdx] = true;
                        foreach ($grid['members'] ?? [] as $member) {
                            foreach ((array) $member as $entry) {
                                if (!empty($entry['id'])) {
                                    $idsABorrar[(string) $entry['id']] = true;
                                }
                            }
                        }
                        continue;
                    }
                    foreach ($grid['members'] ?? [] as $member) {
                        $idsDelSlot = array_map(fn($entry) => (string) ($entry['id'] ?? ''), (array) $member);
                        if (array_intersect($idsDelSlot, array_keys($idsABorrar))) {
                            foreach ($idsDelSlot as $id) {
                                if ($id !== '') {
                                    $idsABorrar[$id] = true;
                                }
                            }
                        }
                    }
                }

                $elementosAntes = count($page['elements']);
                $data['pages'][$pageIdx]['elements'] = array_values(array_filter(
                    $page['elements'],
                    fn($el) => !isset($idsABorrar[(string) ($el['id'] ?? '')])
                ));
                $totalElementosBorrados += $elementosAntes - count($data['pages'][$pageIdx]['elements']);

                if (!empty($page['grids'])) {
                    $grids = [];
                    foreach ($page['grids'] as $gridIdx => $grid) {
                        if (isset($gridsABorrarEnteros[$gridIdx])) {
                            $totalCeldasBorradas += count($grid['members'] ?? []) + 1; // +1 por la plantilla
                            continue;
                        }
                        $members = [];
                        foreach ($grid['members'] ?? [] as $key => $member) {
                            $idsDelSlot = array_map(fn($entry) => (string) ($entry['id'] ?? ''), (array) $member);
                            if (array_intersect($idsDelSlot, array_keys($idsABorrar))) {
                                $totalCeldasBorradas++;
                                continue;
                            }
                            $members[$key] = $member;
                        }
                        $grid['members'] = $members;
                        $grids[] = $grid;
                    }
                    $data['pages'][$pageIdx]['grids'] = $grids;
                }

                if (!empty($page['layout_groups'])) {
                    $finalIds = array_column($data['pages'][$pageIdx]['elements'], 'id');
                    $layoutGroups = array_values(array_filter(array_map(function ($group) use ($finalIds) {
                        $group['members'] = array_values(array_intersect($group['members'] ?? [], $finalIds));
                        return $group;
                    }, $page['layout_groups']), fn($group) => count($group['members']) > 1));
                    $data['pages'][$pageIdx]['layout_groups'] = $layoutGroups;
                }
            }

            if ($totalElementosBorrados > 0) {
                $filas[] = [$design->id, $design->name, $totalElementosBorrados, $totalCeldasBorradas];
                $cambios[$design->id] = $data;
            }
        }

        if (!$filas) {
            $this->info('No hay diseños con elementos fuera de la hoja.');
            return self::SUCCESS;
        }

        $this->table(['Diseño', 'Nombre', 'Elementos borrados', 'Celdas de grilla borradas'], $filas);

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
