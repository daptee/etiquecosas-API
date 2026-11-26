<?php

namespace App\Services;

use App\Models\LabelShape;

/**
 * Resuelve en vivo las "variantes de etiqueta" (label_shapes.data.variants[],
 * ver PDF_VARIANTES_ETIQUETA.md): un elemento `background` con
 * `label_shape_id` + `variant_id` se reconstruye, en memoria y en cada uso
 * (generación de PDF o preview del editor — nunca se persiste), a partir de
 * la definición ACTUAL de esa variante — mismo espíritu que
 * PDF_TEMATICAS_EN_VIVO.md, solo que acá se reemplaza un grupo entero de
 * elementos (la etiqueta + lo que tiene encima), no un campo suelto.
 *
 * Ver PDF_VARIANTES_ETIQUETA_EN_VIVO.md: se eligió que la variante SIEMPRE
 * gana. Cualquier retoque manual hecho en el editor sobre los elementos de
 * ESA etiqueta puntual (después de aplicarle la variante) se pierde en cada
 * resolución. Lo único que se conserva de la instancia ya guardada es dónde
 * está parada (posición/rotación/el id del propio label) y su `group_id` (por
 * si el admin agrupó esa etiqueta entera con algo de afuera). Si la forma o
 * la variante ya no existen, la etiqueta queda tal cual estaba guardada.
 */
class LabelShapeVariantResolver
{
    /** @var array<int, LabelShape|null> */
    private static array $shapeCache = [];

    /** Punto de entrada: resuelve todas las páginas de un diseño (array puro, no persiste nada). */
    public static function resolvePages(array $pages): array
    {
        // El cache de shapes es solo para esta llamada (varias páginas/etiquetas
        // de un mismo diseño suelen compartir shape) — se reinicia en cada
        // entrada para no servir una variante vieja en un worker de cola de
        // vida larga si justo se editó entre una orden y la siguiente.
        self::$shapeCache = [];
        return array_map(fn($page) => self::resolvePage($page), $pages);
    }

    private static function resolvePage(array $page): array
    {
        $elements = $page['elements'] ?? null;
        if (!is_array($elements) || !$elements) {
            return $page;
        }
        $layoutGroups = $page['layout_groups'] ?? [];

        foreach ($elements as $label) {
            if (!is_array($label) || ($label['type'] ?? null) !== 'background') {
                continue;
            }
            if (empty($label['label_shape_id']) || empty($label['variant_id'])) {
                continue;
            }

            $shape = self::findShape((int) $label['label_shape_id']);
            $variant = self::findVariant($shape, (string) $label['variant_id']);
            if (!$shape || !$variant) {
                continue;
            }

            [$elements, $layoutGroups] = self::applyVariant($label, $shape, $variant, $elements, $layoutGroups);
        }

        $page['elements'] = $elements;
        $page['layout_groups'] = $layoutGroups;
        return $page;
    }

    private static function findShape(int $shapeId): ?LabelShape
    {
        if (!array_key_exists($shapeId, self::$shapeCache)) {
            self::$shapeCache[$shapeId] = LabelShape::find($shapeId);
        }
        return self::$shapeCache[$shapeId];
    }

    private static function findVariant(?LabelShape $shape, string $variantId): ?array
    {
        if (!$shape) {
            return null;
        }
        foreach ($shape->data['variants'] ?? [] as $variant) {
            if (($variant['id'] ?? null) === $variantId) {
                return $variant;
            }
        }
        return null;
    }

    /**
     * Reemplaza, dentro de $elements/$layoutGroups, la instancia de $label:
     * sus elementos "de arriba" actuales (mismo criterio geométrico que usa
     * el editor para armar/guardar una variante, ver elementsOnLabel en
     * LabelShapeVariantsHelper.ts) y los layout_groups que son enteramente
     * suyos, por una instancia fresca de la definición ACTUAL de la
     * variante, reanclada a la posición/rotación donde está hoy esa etiqueta.
     */
    private static function applyVariant(array $label, LabelShape $shape, array $variant, array $elements, array $layoutGroups): array
    {
        $labelId = $label['id'] ?? null;
        if (!$labelId) {
            return [$elements, $layoutGroups];
        }

        $onTop = self::elementsOnLabel($label, $elements);
        $onTopIds = array_filter(array_map(fn($el) => $el['id'] ?? null, $onTop));
        $savedIds = array_merge([$labelId], array_values($onTopIds));

        $keepGroups = [];
        foreach ($layoutGroups as $group) {
            if (!self::groupWithin($group, $savedIds)) {
                $keepGroups[] = $group;
            }
        }

        $xCm = (float) ($label['x_cm'] ?? 0);
        $yCm = (float) ($label['y_cm'] ?? 0);
        $rotationDeg = (float) ($label['rotation_deg'] ?? 0);

        $instance = self::instantiate($variant, $shape, $xCm, $yCm, $labelId);
        if ($rotationDeg) {
            $instance = self::rotateInstance($instance, $rotationDeg);
        }

        // La etiqueta conserva su identidad de instancia (id/posición/grupo externo);
        // todo lo demás de "cómo se ve" sale de la variante actual.
        $newLabel = $instance['label'];
        $newLabel['group_id'] = $label['group_id'] ?? null;

        $newElements = [];
        foreach ($elements as $el) {
            $id = is_array($el) ? ($el['id'] ?? null) : null;
            if ($id === $labelId) {
                $newElements[] = $newLabel;
                foreach ($instance['elements'] as $fresh) {
                    $newElements[] = $fresh;
                }
                continue;
            }
            if (in_array($id, $onTopIds, true)) {
                continue;
            }
            $newElements[] = $el;
        }

        $newLayoutGroups = array_merge($keepGroups, $instance['layoutGroups']);

        return [$newElements, $newLayoutGroups];
    }

    private static function groupWithin(array $group, array $ids): bool
    {
        $members = $group['members'] ?? [];
        if (!$members) {
            return false;
        }
        foreach ($members as $memberId) {
            if (!in_array($memberId, $ids, true)) {
                return false;
            }
        }
        $containerId = $group['container_element_id'] ?? null;
        if ($containerId && !in_array($containerId, $ids, true)) {
            return false;
        }
        return true;
    }

    /** Mismo criterio que elementsOnLabel (LabelShapeVariantsHelper.ts): el centro del elemento, visto desde el marco sin rotar del label, cae dentro de su rectángulo. */
    private static function elementsOnLabel(array $label, array $elements): array
    {
        $lw = (float) ($label['width_cm'] ?? 0);
        $lh = (float) ($label['height_cm'] ?? 0);
        $lx = (float) ($label['x_cm'] ?? 0);
        $ly = (float) ($label['y_cm'] ?? 0);
        $lcx = $lx + $lw / 2;
        $lcy = $ly + $lh / 2;
        $angle = (float) ($label['rotation_deg'] ?? 0);
        $labelId = $label['id'] ?? null;

        $onTop = [];
        foreach ($elements as $el) {
            if (!is_array($el)) {
                continue;
            }
            if (($el['id'] ?? null) === $labelId || ($el['type'] ?? null) === 'background') {
                continue;
            }
            $cx = (float) ($el['x_cm'] ?? 0) + (float) ($el['width_cm'] ?? 0) / 2;
            $cy = (float) ($el['y_cm'] ?? 0) + (float) ($el['height_cm'] ?? 0) / 2;
            $point = self::rotatePoint($cx, $cy, $lcx, $lcy, -$angle);

            if ($point['x'] >= $lx && $point['x'] <= $lx + $lw && $point['y'] >= $ly && $point['y'] <= $ly + $lh) {
                $onTop[] = $el;
            }
        }
        return $onTop;
    }

    private static function rotatePoint(float $x, float $y, float $cx, float $cy, float $deg): array
    {
        $rad = deg2rad($deg);
        $cos = cos($rad);
        $sin = sin($rad);
        $dx = $x - $cx;
        $dy = $y - $cy;
        return [
            'x' => $cx + $dx * $cos - $dy * $sin,
            'y' => $cy + $dx * $sin + $dy * $cos,
        ];
    }

    private static function uid(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(6));
    }

    /**
     * Copia fresca de la variante lista para pisar la instancia ubicada en
     * x/y: ids nuevos para cada sub-elemento (el label reusa $labelId),
     * posiciones absolutas. El label toma el tamaño/forma ACTUAL del
     * label_shape, igual que instantiateLabelVariant (front).
     */
    private static function instantiate(array $variant, LabelShape $shape, float $xCm, float $yCm, string $labelId): array
    {
        $variantLabel = $variant['label'] ?? [];
        $idMap = [($variantLabel['id'] ?? '__label__') => $labelId];
        foreach ($variant['elements'] ?? [] as $el) {
            if (isset($el['id'])) {
                $idMap[$el['id']] = self::uid('el');
            }
        }
        $groupMap = [];
        $mapGroup = function ($groupId) use (&$groupMap) {
            if (!$groupId) {
                return null;
            }
            if (!isset($groupMap[$groupId])) {
                $groupMap[$groupId] = self::uid('group');
            }
            return $groupMap[$groupId];
        };

        $place = function (array $el) use ($idMap, $mapGroup, $xCm, $yCm) {
            $el['id'] = $idMap[$el['id'] ?? null] ?? self::uid('el');
            $el['group_id'] = $mapGroup($el['group_id'] ?? null);
            $el['x_cm'] = round(($el['x_cm'] ?? 0) + $xCm, 2);
            $el['y_cm'] = round(($el['y_cm'] ?? 0) + $yCm, 2);
            return $el;
        };

        $label = $place($variantLabel);
        $label['type'] = 'background';
        $label['label_shape_id'] = $shape->id;
        $label['variant_id'] = $variant['id'] ?? null;
        $label['width_cm'] = (float) $shape->width_cm;
        $label['height_cm'] = (float) $shape->height_cm;

        $elements = array_map($place, $variant['elements'] ?? []);

        $layoutGroups = [];
        foreach ($variant['layout_groups'] ?? [] as $group) {
            $members = $group['members'] ?? [];
            $allMapped = true;
            foreach ($members as $m) {
                if (!isset($idMap[$m])) {
                    $allMapped = false;
                    break;
                }
            }
            if (!$allMapped) {
                continue;
            }
            $newGroup = [
                'id' => self::uid('layout'),
                'members' => array_map(fn($m) => $idMap[$m], $members),
                'links' => $group['links'] ?? [],
            ];
            if (!empty($group['anchor'])) {
                $newGroup['anchor'] = [
                    'x_cm' => round(($group['anchor']['x_cm'] ?? 0) + $xCm, 2),
                    'y_cm' => round(($group['anchor']['y_cm'] ?? 0) + $yCm, 2),
                ];
            }
            if (!empty($group['container_element_id']) && isset($idMap[$group['container_element_id']])) {
                $newGroup['container_element_id'] = $idMap[$group['container_element_id']];
            }
            $layoutGroups[] = $newGroup;
        }

        return ['label' => $label, 'elements' => $elements, 'layoutGroups' => $layoutGroups];
    }

    /** Gira una instancia recién armada (sin rotación propia) para que su label termine en $angleDeg — mismo criterio que rotatePlacedLabelVariant (front). */
    private static function rotateInstance(array $instance, float $angleDeg): array
    {
        $label = $instance['label'];
        $delta = $angleDeg - (float) ($label['rotation_deg'] ?? 0);
        $label['rotation_deg'] = self::normalizeDeg($angleDeg);
        if (abs(self::normalizeDeg($delta)) < 1e-9) {
            return ['label' => $label, 'elements' => $instance['elements'], 'layoutGroups' => $instance['layoutGroups']];
        }

        $lcx = (float) ($label['x_cm'] ?? 0) + (float) ($label['width_cm'] ?? 0) / 2;
        $lcy = (float) ($label['y_cm'] ?? 0) + (float) ($label['height_cm'] ?? 0) / 2;

        $elements = array_map(function ($el) use ($lcx, $lcy, $delta) {
            $cx = (float) ($el['x_cm'] ?? 0) + (float) ($el['width_cm'] ?? 0) / 2;
            $cy = (float) ($el['y_cm'] ?? 0) + (float) ($el['height_cm'] ?? 0) / 2;
            $center = self::rotatePoint($cx, $cy, $lcx, $lcy, $delta);
            $el['x_cm'] = round($center['x'] - ($el['width_cm'] ?? 0) / 2, 2);
            $el['y_cm'] = round($center['y'] - ($el['height_cm'] ?? 0) / 2, 2);
            $el['rotation_deg'] = self::normalizeDeg(($el['rotation_deg'] ?? 0) + $delta);
            return $el;
        }, $instance['elements']);

        $layoutGroups = array_map(function ($group) use ($lcx, $lcy, $delta) {
            if (empty($group['anchor'])) {
                return $group;
            }
            $anchor = self::rotatePoint((float) $group['anchor']['x_cm'], (float) $group['anchor']['y_cm'], $lcx, $lcy, $delta);
            $group['anchor'] = ['x_cm' => round($anchor['x'], 2), 'y_cm' => round($anchor['y'], 2)];
            return $group;
        }, $instance['layoutGroups']);

        return ['label' => $label, 'elements' => $elements, 'layoutGroups' => $layoutGroups];
    }

    private static function normalizeDeg(float $deg): float
    {
        $n = fmod(fmod($deg + 180, 360) + 360, 360) - 180;
        return abs($n) < 1e-9 ? 0.0 : round($n, 2);
    }
}
