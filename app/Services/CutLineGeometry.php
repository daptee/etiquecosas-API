<?php

namespace App\Services;

/**
 * Geometría de las líneas de corte de una página ya resuelta del editor
 * (ver EtiquetaService::resolverPaginasDesign). Las líneas salen de las
 * etiquetas (elementos type "background"), no hay elementos propios.
 *
 * Todo en cm, origen arriba a la izquierda y eje Y hacia abajo, igual que
 * el diseño. Cada trazado es una lista de segmentos:
 *   ['M', x, y] | ['L', x, y] | ['C', x1, y1, x2, y2, x, y] | ['Z']
 */
class CutLineGeometry
{
    public const DEFAULT_WIDTH_CM = 0.03;
    public const DEFAULT_COLOR_HEX = '#FF00FF';
    private const MIN_SIZE_CM = 0.1;
    // Aproximación de un cuarto de círculo con una bezier cúbica.
    private const KAPPA = 0.5522847498;

    /**
     * Trazo de la página (page.cut_lines), o null si la página no dibuja
     * líneas. color: ['space' => 'rgb'|'cmyk', 'values' => [0..1, ...]].
     */
    public static function configPagina(array $page): ?array
    {
        $cut = $page['cut_lines'] ?? null;
        if (!is_array($cut) || ($cut['enabled'] ?? false) !== true) {
            return null;
        }

        $width = isset($cut['width_cm']) && is_numeric($cut['width_cm']) && $cut['width_cm'] > 0
            ? (float) $cut['width_cm']
            : self::DEFAULT_WIDTH_CM;

        return [
            'width_cm' => $width,
            'color' => self::parsearColor($cut['color'] ?? null),
        ];
    }

    /**
     * Trazados de todas las etiquetas de la página con línea visible.
     */
    public static function trazadosPagina(array $page): array
    {
        $trazados = [];
        foreach ($page['elements'] ?? [] as $el) {
            if (($el['type'] ?? null) !== 'background') {
                continue;
            }
            if (($el['cut_line'] ?? 'auto') === 'hidden') {
                continue;
            }
            $trazados[] = self::trazadoEtiqueta($el);
        }
        return $trazados;
    }

    private static function trazadoEtiqueta(array $el): array
    {
        $v = (float) ($el['cut_vertical_cm'] ?? 0);
        $h = (float) ($el['cut_horizontal_cm'] ?? 0);
        $elX = (float) ($el['x_cm'] ?? 0);
        $elY = (float) ($el['y_cm'] ?? 0);
        $elW = (float) ($el['width_cm'] ?? 1);
        $elH = (float) ($el['height_cm'] ?? 1);

        // La caja crece/se encoge simétrica: mismo centro que la etiqueta.
        $w = max(self::MIN_SIZE_CM, $elW + 2 * $h);
        $hh = max(self::MIN_SIZE_CM, $elH + 2 * $v);
        $cx = $elX + $elW / 2;
        $cy = $elY + $elH / 2;
        $x = $cx - $w / 2;
        $y = $cy - $hh / 2;

        $shapeType = $el['resolved_shape_type'] ?? 'rect';

        if ($shapeType === 'circle') {
            $trazado = self::elipse($cx, $cy, $w / 2, $hh / 2);
        } elseif ($shapeType === 'custom') {
            $trazado = self::trazadoCustom($el, $x, $y, $w, $hh) ?? self::rectRedondeado($x, $y, $w, $hh, 0);
        } else {
            $trazado = self::rectRedondeado($x, $y, $w, $hh, self::radioLinea($el, $elW, $elH, $v, $h));
        }

        $rotation = (float) ($el['rotation_deg'] ?? 0);
        return $rotation ? self::rotar($trazado, $cx, $cy, $rotation) : $trazado;
    }

    private static function radioLinea(array $el, float $elW, float $elH, float $v, float $h): float
    {
        $mode = $el['radius_mode'] ?? null;
        if ($mode === 'straight') {
            $base = 0.0;
        } elseif ($mode === 'rounded') {
            $base = min($elW, $elH) * ((float) ($el['radius_pct'] ?? 14)) / 100;
        } else {
            $base = (float) ($el['resolved_shape_corner_radius_cm'] ?? 0);
        }

        return $base > 0 ? max(0, $base + min($v, $h)) : 0.0;
    }

    private static function rectRedondeado(float $x, float $y, float $w, float $h, float $r): array
    {
        $r = min($r, $w / 2, $h / 2);
        $x2 = $x + $w;
        $y2 = $y + $h;

        if ($r <= 0) {
            return [['M', $x, $y], ['L', $x2, $y], ['L', $x2, $y2], ['L', $x, $y2], ['Z']];
        }

        $k = $r * self::KAPPA;
        return [
            ['M', $x + $r, $y],
            ['L', $x2 - $r, $y],
            ['C', $x2 - $r + $k, $y, $x2, $y + $r - $k, $x2, $y + $r],
            ['L', $x2, $y2 - $r],
            ['C', $x2, $y2 - $r + $k, $x2 - $r + $k, $y2, $x2 - $r, $y2],
            ['L', $x + $r, $y2],
            ['C', $x + $r - $k, $y2, $x, $y2 - $r + $k, $x, $y2 - $r],
            ['L', $x, $y + $r],
            ['C', $x, $y + $r - $k, $x + $r - $k, $y, $x + $r, $y],
            ['Z'],
        ];
    }

    private static function elipse(float $cx, float $cy, float $rx, float $ry): array
    {
        $kx = $rx * self::KAPPA;
        $ky = $ry * self::KAPPA;
        return [
            ['M', $cx + $rx, $cy],
            ['C', $cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry],
            ['C', $cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy],
            ['C', $cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry],
            ['C', $cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy],
            ['Z'],
        ];
    }

    /**
     * label_shapes.data.outline_svg: path cerrado (M/L/Z) en cm relativos al
     * width_cm × height_cm de la forma, escalado a la caja de la línea.
     * null si no se puede leer (el caller dibuja un rectángulo).
     */
    private static function trazadoCustom(array $el, float $x, float $y, float $w, float $h): ?array
    {
        $d = $el['resolved_shape_outline_svg'] ?? null;
        $shapeW = (float) ($el['resolved_shape_width_cm'] ?? 0);
        $shapeH = (float) ($el['resolved_shape_height_cm'] ?? 0);
        if (!is_string($d) || $d === '' || $shapeW <= 0 || $shapeH <= 0) {
            return null;
        }

        // Por si viene el <path> entero en vez de solo el atributo d.
        if (str_contains($d, '<')) {
            if (!preg_match('/\sd\s*=\s*"([^"]*)"/', $d, $m)) {
                return null;
            }
            $d = $m[1];
        }

        $puntos = self::parsearPath($d);
        if (!$puntos) {
            return null;
        }

        $sx = $w / $shapeW;
        $sy = $h / $shapeH;
        return array_map(
            fn($seg) => $seg[0] === 'Z' ? $seg : [$seg[0], $x + $seg[1] * $sx, $y + $seg[2] * $sy],
            $puntos
        );
    }

    /**
     * Parser mínimo de path SVG: M/L/H/V/Z, absolutos y relativos. Cualquier
     * otro comando (curvas, arcos) = no se puede leer → null.
     */
    private static function parsearPath(string $d): ?array
    {
        if (preg_match('/[^MmLlHhVvZz0-9eE.,+\-\s]/', $d)) {
            return null;
        }
        preg_match_all('/[MmLlHhVvZz]|[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/', $d, $m);
        $tokens = $m[0];

        $segs = [];
        $cmd = null;
        $cx = $cy = 0.0;
        $startX = $startY = 0.0;
        $i = 0;
        $n = count($tokens);
        $num = function () use (&$i, $tokens, $n) {
            if ($i >= $n || !is_numeric($tokens[$i])) {
                throw new \InvalidArgumentException('path inválido');
            }
            return (float) $tokens[$i++];
        };

        try {
            while ($i < $n) {
                if (!is_numeric($tokens[$i])) {
                    $cmd = $tokens[$i++];
                } elseif ($cmd === null || $cmd === 'Z' || $cmd === 'z') {
                    return null;
                }

                switch ($cmd) {
                    case 'M':
                    case 'm':
                        $px = $num();
                        $py = $num();
                        if ($cmd === 'm' && $segs) {
                            $px += $cx;
                            $py += $cy;
                        }
                        $cx = $startX = $px;
                        $cy = $startY = $py;
                        $segs[] = ['M', $cx, $cy];
                        // Pares que siguen a un M son L implícitos.
                        $cmd = $cmd === 'm' ? 'l' : 'L';
                        break;
                    case 'L':
                    case 'l':
                        $px = $num();
                        $py = $num();
                        $cx = $cmd === 'l' ? $cx + $px : $px;
                        $cy = $cmd === 'l' ? $cy + $py : $py;
                        $segs[] = ['L', $cx, $cy];
                        break;
                    case 'H':
                    case 'h':
                        $px = $num();
                        $cx = $cmd === 'h' ? $cx + $px : $px;
                        $segs[] = ['L', $cx, $cy];
                        break;
                    case 'V':
                    case 'v':
                        $py = $num();
                        $cy = $cmd === 'v' ? $cy + $py : $py;
                        $segs[] = ['L', $cx, $cy];
                        break;
                    case 'Z':
                    case 'z':
                        $segs[] = ['Z'];
                        $cx = $startX;
                        $cy = $startY;
                        break;
                    default:
                        return null;
                }
            }
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        if (!$segs || $segs[0][0] !== 'M') {
            return null;
        }
        if (end($segs)[0] !== 'Z') {
            $segs[] = ['Z'];
        }
        return $segs;
    }

    /**
     * Rota sobre (cx, cy), en grados, sentido horario con Y hacia abajo
     * (igual que transform: rotate() del render).
     */
    private static function rotar(array $trazado, float $cx, float $cy, float $deg): array
    {
        $rad = deg2rad($deg);
        $cos = cos($rad);
        $sin = sin($rad);

        return array_map(function ($seg) use ($cx, $cy, $cos, $sin) {
            $out = [$seg[0]];
            for ($i = 1; $i < count($seg); $i += 2) {
                $dx = $seg[$i] - $cx;
                $dy = $seg[$i + 1] - $cy;
                $out[] = $cx + $dx * $cos - $dy * $sin;
                $out[] = $cy + $dx * $sin + $dy * $cos;
            }
            return $out;
        }, $trazado);
    }

    private static function parsearColor($color): array
    {
        if (is_array($color) && ($color['mode'] ?? null) === 'cmyk' && is_string($color['value'] ?? null)) {
            $partes = array_map('trim', explode(',', $color['value']));
            if (count($partes) === 4 && count(array_filter($partes, 'is_numeric')) === 4) {
                return ['space' => 'cmyk', 'values' => array_map(fn($p) => max(0, min(1, (float) $p)), $partes)];
            }
        }

        $hex = is_array($color) && ($color['mode'] ?? 'hex') === 'hex' && is_string($color['value'] ?? null)
            ? $color['value']
            : self::DEFAULT_COLOR_HEX;
        return ['space' => 'rgb', 'values' => self::hexARgb($hex) ?? self::hexARgb(self::DEFAULT_COLOR_HEX)];
    }

    private static function hexARgb(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }
        return array_map(fn($c) => hexdec($c) / 255, str_split($hex, 2));
    }
}
