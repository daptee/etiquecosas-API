<?php

namespace App\Services;

use App\Models\LabelShape;
use App\Models\ProductPdfDesign;

/**
 * Propagación de variantes de etiqueta (ver PDF_VARIANTES_ETIQUETA_PROPAGACION.md,
 * propuesta del front). Reemplaza el intento anterior de "resolución en vivo"
 * (LabelShapeVariantResolver, nunca llegó a usarse en producción): en vez de
 * reconstruir la etiqueta en memoria en cada lectura, al GUARDAR una variante
 * se reescriben y persisten los diseños que la usan.
 *
 * Cada etiqueta/elemento que vino de una variante lleva `label_variant_ref`
 * (ver PdfDesignSanitizer::sanitizeLabelVariantRef). `instance_id` une la
 * etiqueta con sus elementos dentro de UNA página; puede haber varias
 * instancias de la misma variante en el mismo diseño (ej. una grilla).
 */
class LabelShapeVariantPropagator
{
    private const LABEL_PART_KEYS = [
        'fill' => ['fill', 'color', 'background_image'],
        'border' => ['border'],
        'corners' => ['radius_mode', 'radius_pct'],
        'padding' => ['padding_vertical_cm', 'padding_horizontal_cm', 'padding_cm'],
        'cut' => ['cut_line', 'cut_vertical_cm', 'cut_horizontal_cm'],
    ];

    /**
     * Diseños que usan esta variante (para GET .../usage), sin modificar nada.
     */
    public static function usageForVariant(int $labelShapeId, string $variantId): array
    {
        $usage = [];
        foreach (self::candidateDesigns($variantId) as $design) {
            foreach (($design->data['pages'] ?? []) as $page) {
                foreach (($page['elements'] ?? []) as $el) {
                    if (self::refMatches($el, $labelShapeId, $variantId)) {
                        $usage[$design->id] = ['id' => $design->id, 'name' => $design->name];
                        continue 2;
                    }
                }
            }
        }
        return array_values($usage);
    }

    /**
     * Reescribe y persiste (`$design->save()`) cada diseño que use esta
     * variante, con la definición NUEVA. Devuelve los diseños actualizados
     * (id, name) para informar al front (`updated_designs`).
     */
    public static function propagateVariant(LabelShape $shape, array $variant): array
    {
        $variantId = (string) ($variant['id'] ?? '');
        if ($variantId === '') {
            return [];
        }

        $updated = [];
        foreach (self::candidateDesigns($variantId) as $design) {
            $data = $design->data;
            if (empty($data['pages']) || !is_array($data['pages'])) {
                continue;
            }

            $changed = false;
            foreach ($data['pages'] as $pi => $page) {
                if (empty($page['elements']) || !is_array($page['elements'])) {
                    continue;
                }
                $elements = array_values($page['elements']);
                $layoutGroups = is_array($page['layout_groups'] ?? null) ? $page['layout_groups'] : [];

                $labelIds = [];
                foreach ($elements as $el) {
                    if (self::refMatches($el, $shape->id, $variantId)) {
                        $labelIds[] = $el['id'];
                    }
                }
                if (!$labelIds) {
                    continue;
                }

                foreach ($labelIds as $labelId) {
                    [$elements, $layoutGroups] = self::propagateInstance($labelId, $variant, $elements, $layoutGroups);
                }

                $page['elements'] = $elements;
                $page['layout_groups'] = $layoutGroups;
                $data['pages'][$pi] = $page;
                $changed = true;
            }

            if ($changed) {
                $design->data = $data;
                $design->save();
                $updated[$design->id] = ['id' => $design->id, 'name' => $design->name];
            }
        }

        return array_values($updated);
    }

    /**
     * Se llama cuando una variante se borra (o se borra la forma base entera,
     * con $variantId = null para todas). Los diseños que la usaban quedan tal
     * cual están, solo se les saca la referencia (label_variant_ref de la
     * etiqueta y sus elementos, label_variant_instance_id de sus relaciones)
     * para que no se vuelvan a propagar contra algo que ya no existe.
     */
    public static function unlinkVariant(int $labelShapeId, ?string $variantId): array
    {
        $candidates = $variantId !== null
            ? self::candidateDesigns($variantId)
            : ProductPdfDesign::all(['id', 'name', 'data']);

        $updated = [];
        foreach ($candidates as $design) {
            $data = $design->data;
            if (empty($data['pages']) || !is_array($data['pages'])) {
                continue;
            }

            $changed = false;
            foreach ($data['pages'] as $pi => $page) {
                if (empty($page['elements']) || !is_array($page['elements'])) {
                    continue;
                }
                $elements = $page['elements'];
                $layoutGroups = is_array($page['layout_groups'] ?? null) ? $page['layout_groups'] : [];
                $instanceIds = [];

                foreach ($elements as $idx => $el) {
                    if (!is_array($el) || ($el['type'] ?? null) !== 'background') {
                        continue;
                    }
                    $ref = $el['label_variant_ref'] ?? null;
                    if (!is_array($ref) || (int) ($ref['label_shape_id'] ?? 0) !== $labelShapeId) {
                        continue;
                    }
                    if ($variantId !== null && (string) ($ref['variant_id'] ?? '') !== $variantId) {
                        continue;
                    }
                    $instanceIds[(string) ($ref['instance_id'] ?? '')] = true;
                    unset($elements[$idx]['label_variant_ref']);
                    $changed = true;
                }
                if (!$instanceIds) {
                    continue;
                }

                foreach ($elements as $idx => $el) {
                    $ref = is_array($el) ? ($el['label_variant_ref'] ?? null) : null;
                    if (is_array($ref) && isset($instanceIds[(string) ($ref['instance_id'] ?? '')])) {
                        unset($elements[$idx]['label_variant_ref']);
                    }
                }
                foreach ($layoutGroups as $gi => $g) {
                    if (is_array($g) && isset($instanceIds[(string) ($g['label_variant_instance_id'] ?? '')])) {
                        unset($layoutGroups[$gi]['label_variant_instance_id']);
                    }
                }

                $page['elements'] = array_values($elements);
                $page['layout_groups'] = array_values($layoutGroups);
                $data['pages'][$pi] = $page;
            }

            if ($changed) {
                $design->data = $data;
                $design->save();
                $updated[$design->id] = ['id' => $design->id, 'name' => $design->name];
            }
        }

        return array_values($updated);
    }

    /**
     * Pre-filtro barato (LIKE sobre el JSON) antes de parsear cada diseño.
     * A esta escala (cientos de diseños) alcanza; si el catálogo crece mucho,
     * reemplazar por una tabla auxiliar indexada.
     */
    private static function candidateDesigns(string $variantId)
    {
        return ProductPdfDesign::where('data', 'like', '%' . $variantId . '%')->get(['id', 'name', 'data']);
    }

    private static function refMatches($el, int $labelShapeId, string $variantId): bool
    {
        if (!is_array($el) || ($el['type'] ?? null) !== 'background') {
            return false;
        }
        $ref = $el['label_variant_ref'] ?? null;
        return is_array($ref)
            && (int) ($ref['label_shape_id'] ?? 0) === $labelShapeId
            && (string) ($ref['variant_id'] ?? '') === $variantId;
    }

    /**
     * Actualiza UNA instancia (una etiqueta + sus elementos vinculados, ver
     * `instance_id`) dentro de los elementos/relaciones de una página. Busca
     * la etiqueta por id en vez de por índice porque instancias previas de
     * este mismo loop ya pueden haber agregado/sacado elementos.
     */
    private static function propagateInstance(string $labelId, array $variant, array $elements, array $layoutGroups): array
    {
        $labelIdx = null;
        foreach ($elements as $idx => $el) {
            if (is_array($el) && ($el['id'] ?? null) === $labelId) {
                $labelIdx = $idx;
                break;
            }
        }
        if ($labelIdx === null) {
            return [$elements, $layoutGroups];
        }

        $label = $elements[$labelIdx];
        $ref = $label['label_variant_ref'];
        $instanceId = (string) ($ref['instance_id'] ?? '');
        $labelParts = is_array($ref['label_parts'] ?? null) ? $ref['label_parts'] : [];
        $elementIds = is_array($ref['element_ids'] ?? null) ? $ref['element_ids'] : [];
        $allElements = ($ref['all_elements'] ?? false) === true;

        $variantLabel = is_array($variant['label'] ?? null) ? $variant['label'] : [];
        $variantElementsById = [];
        foreach (($variant['elements'] ?? []) as $ve) {
            if (is_array($ve) && isset($ve['id'])) {
                $variantElementsById[(string) $ve['id']] = $ve;
            }
        }

        // 1. La etiqueta: solo se tocan las partes en label_parts.
        foreach ($labelParts as $part) {
            foreach (self::LABEL_PART_KEYS[$part] ?? [] as $key) {
                if (array_key_exists($key, $variantLabel)) {
                    $label[$key] = $variantLabel[$key];
                } else {
                    unset($label[$key]);
                }
            }
        }

        $delta = (float) ($label['rotation_deg'] ?? 0) - (float) ($variantLabel['rotation_deg'] ?? 0);

        // 2. Elementos vinculados a esta instancia, indexados por su id EN LA VARIANTE.
        $byVariantElementId = [];
        foreach ($elements as $idx => $el) {
            if (!is_array($el)) {
                continue;
            }
            $elRef = $el['label_variant_ref'] ?? null;
            if (is_array($elRef) && ($elRef['instance_id'] ?? null) === $instanceId && isset($elRef['variant_element_id'])) {
                $byVariantElementId[(string) $elRef['variant_element_id']] = $idx;
            }
        }

        $newElementIds = [];
        $toRemove = [];

        foreach ($elementIds as $variantElementId) {
            $variantElementId = (string) $variantElementId;
            $designIdx = $byVariantElementId[$variantElementId] ?? null;

            if (!isset($variantElementsById[$variantElementId])) {
                // Se borró de la variante.
                if ($designIdx !== null) {
                    $toRemove[$designIdx] = true;
                }
                continue;
            }
            if ($designIdx === null) {
                // El admin lo borró o lo desvinculó a mano: no se toca.
                $newElementIds[] = $variantElementId;
                continue;
            }

            $fresh = self::placeVariantElement($variantElementsById[$variantElementId], $label, $delta, $instanceId);
            $fresh['id'] = $elements[$designIdx]['id'];
            $fresh['label_variant_ref'] = $elements[$designIdx]['label_variant_ref'];
            $elements[$designIdx] = $fresh;
            $newElementIds[] = $variantElementId;
        }

        if ($allElements) {
            foreach ($variantElementsById as $variantElementId => $ve) {
                if (in_array($variantElementId, $elementIds, true)) {
                    continue;
                }
                $fresh = self::placeVariantElement($ve, $label, $delta, $instanceId);
                $fresh['id'] = self::uid('el');
                $fresh['label_variant_ref'] = ['instance_id' => $instanceId, 'variant_element_id' => $variantElementId];
                $elements[] = $fresh;
                $newElementIds[] = $variantElementId;
            }
        }

        foreach ($toRemove as $idx => $_) {
            unset($elements[$idx]);
        }
        $elements = array_values($elements);

        // Reubicar la etiqueta (por id, el índice original puede haber quedado obsoleto).
        foreach ($elements as $idx => $el) {
            if (is_array($el) && ($el['id'] ?? null) === $labelId) {
                $label['label_variant_ref']['element_ids'] = $newElementIds;
                $elements[$idx] = $label;
                break;
            }
        }

        // 3. Relaciones (layout_groups): se tiran las de esta instancia y se
        // recrean desde variant.layout_groups, solo las que mapeen enteras.
        $layoutGroups = array_values(array_filter(
            $layoutGroups,
            fn($g) => !is_array($g) || (string) ($g['label_variant_instance_id'] ?? '') !== $instanceId
        ));

        $idMap = [(string) ($variantLabel['id'] ?? '') => $labelId];
        foreach ($elements as $el) {
            $r = is_array($el) ? ($el['label_variant_ref'] ?? null) : null;
            if (is_array($r) && ($r['instance_id'] ?? null) === $instanceId && isset($r['variant_element_id'])) {
                $idMap[(string) $r['variant_element_id']] = $el['id'];
            }
        }

        foreach (($variant['layout_groups'] ?? []) as $vg) {
            if (!is_array($vg) || !is_array($vg['members'] ?? null) || !$vg['members']) {
                continue;
            }

            $mappedMembers = [];
            $ok = true;
            foreach ($vg['members'] as $m) {
                if (!isset($idMap[(string) $m])) {
                    $ok = false;
                    break;
                }
                $mappedMembers[] = $idMap[(string) $m];
            }
            if (!$ok) {
                continue;
            }

            $mappedContainer = null;
            $containerId = $vg['container_element_id'] ?? null;
            if ($containerId !== null && $containerId !== '') {
                if (!isset($idMap[(string) $containerId])) {
                    continue;
                }
                $mappedContainer = $idMap[(string) $containerId];
            }

            $newGroup = $vg;
            $newGroup['id'] = self::uid('layout');
            $newGroup['members'] = $mappedMembers;
            $newGroup['container_element_id'] = $mappedContainer;
            $newGroup['label_variant_instance_id'] = $instanceId;

            if (is_array($vg['anchor'] ?? null)) {
                [$ax, $ay] = self::placePoint(
                    (float) ($vg['anchor']['x_cm'] ?? 0),
                    (float) ($vg['anchor']['y_cm'] ?? 0),
                    $label,
                    $delta
                );
                $newGroup['anchor'] = ['x_cm' => $ax, 'y_cm' => $ay];
            }

            $layoutGroups[] = $newGroup;
        }

        return [$elements, $layoutGroups];
    }

    /**
     * Copia un elemento de la variante (posición relativa a 0,0) a la
     * posición/rotación de la etiqueta del diseño `$label`, igual que
     * `instantiateLabelVariant` + `rotatePlacedLabelVariant` del front.
     * `group_id` (Agrupar, distinto de layout_groups/Relaciones) se vuelve
     * determinístico por instancia para no colisionar con otra instancia de
     * la misma variante.
     */
    private static function placeVariantElement(array $e, array $label, float $delta, string $instanceId): array
    {
        $x = (float) ($e['x_cm'] ?? 0) + (float) ($label['x_cm'] ?? 0);
        $y = (float) ($e['y_cm'] ?? 0) + (float) ($label['y_cm'] ?? 0);
        $widthCm = (float) ($e['width_cm'] ?? 0);
        $heightCm = (float) ($e['height_cm'] ?? 0);

        if (abs($delta) > 0.0001) {
            $lcx = (float) ($label['x_cm'] ?? 0) + (float) ($label['width_cm'] ?? 0) / 2;
            $lcy = (float) ($label['y_cm'] ?? 0) + (float) ($label['height_cm'] ?? 0) / 2;
            $cx = $x + $widthCm / 2;
            $cy = $y + $heightCm / 2;
            [$ncx, $ncy] = self::rotatePoint($cx, $cy, $lcx, $lcy, $delta);
            $x = $ncx - $widthCm / 2;
            $y = $ncy - $heightCm / 2;
            $e['rotation_deg'] = self::normalizeDeg((float) ($e['rotation_deg'] ?? 0) + $delta);
        }

        $e['x_cm'] = round($x, 2);
        $e['y_cm'] = round($y, 2);

        if (isset($e['group_id']) && $e['group_id'] !== null && $e['group_id'] !== '') {
            $e['group_id'] = $instanceId . ':' . $e['group_id'];
        }

        return $e;
    }

    /** Traslada un punto (relativo a 0,0 de la etiqueta) a la posición/rotación de `$label`. */
    private static function placePoint(float $xRel, float $yRel, array $label, float $delta): array
    {
        $x = $xRel + (float) ($label['x_cm'] ?? 0);
        $y = $yRel + (float) ($label['y_cm'] ?? 0);

        if (abs($delta) > 0.0001) {
            $lcx = (float) ($label['x_cm'] ?? 0) + (float) ($label['width_cm'] ?? 0) / 2;
            $lcy = (float) ($label['y_cm'] ?? 0) + (float) ($label['height_cm'] ?? 0) / 2;
            [$x, $y] = self::rotatePoint($x, $y, $lcx, $lcy, $delta);
        }

        return [round($x, 2), round($y, 2)];
    }

    private static function rotatePoint(float $x, float $y, float $cx, float $cy, float $deg): array
    {
        $rad = deg2rad($deg);
        $cos = cos($rad);
        $sin = sin($rad);
        $dx = $x - $cx;
        $dy = $y - $cy;
        return [
            $cx + $dx * $cos - $dy * $sin,
            $cy + $dx * $sin + $dy * $cos,
        ];
    }

    private static function normalizeDeg(float $deg): float
    {
        $deg = fmod($deg, 360);
        if ($deg > 180) {
            $deg -= 360;
        } elseif ($deg < -180) {
            $deg += 360;
        }
        $deg = round($deg, 2);
        return $deg === -0.0 || abs($deg) < 0.01 ? 0.0 : $deg;
    }

    private static function uid(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(6));
    }
}
