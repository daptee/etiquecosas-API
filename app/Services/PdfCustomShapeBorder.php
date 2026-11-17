<?php

namespace App\Services;

use Dompdf\Adapter\CPDF as CpdfCanvas;
use Dompdf\Canvas;
use Dompdf\Css\Color;
use Dompdf\Frame;

/**
 * Borde (`border`) de una etiqueta con forma `custom` (label_shapes.data.outline_svg).
 *
 * El editor (PdfDesignElementGlyph.vue) le pone un borde CSS común a la caja
 * del elemento y después recorta todo con el contorno (clip-path): el borde
 * se ve solo donde el contorno pasa por adentro de esa franja. Dompdf no
 * tiene clip-path y php-svg-lib no recorta, así que se reproduce a mano: al
 * terminar de dibujar el contenedor marcado por RENDER.blade.php se recorta
 * con el contorno (recorte nativo de PDF) y se pintan las cuatro franjas del
 * borde de la caja. Mismo color que el resto (CMYK incluido).
 */
class PdfCustomShapeBorder
{
    public const ATTR_WIDTH = 'data-shape-border-width-pt';
    public const ATTR_COLOR = 'data-shape-border-color';
    public const ATTR_OUTLINE = 'data-shape-outline';
    public const ATTR_SHAPE_W = 'data-shape-width-cm';
    public const ATTR_SHAPE_H = 'data-shape-height-cm';

    private const CM_TO_PT = 72 / 2.54;

    public static function anchoPt(float $widthCm): float
    {
        return round($widthCm * self::CM_TO_PT, 4);
    }

    public static function alTerminarFrame(Frame $frame, Canvas $canvas): void
    {
        if (!$canvas instanceof CpdfCanvas || $frame->is_text_node()) {
            return;
        }
        $node = $frame->get_node();
        if (!$node instanceof \DOMElement || !$node->hasAttribute(self::ATTR_OUTLINE)) {
            return;
        }

        $b = (float) $node->getAttribute(self::ATTR_WIDTH);
        $color = Color::parse($node->getAttribute(self::ATTR_COLOR));
        if ($b <= 0 || !is_array($color)) {
            return;
        }

        [$x, $y, $w, $h] = $frame->get_border_box();
        $trazado = CutLineGeometry::trazadoCustom([
            'resolved_shape_outline_svg' => $node->getAttribute(self::ATTR_OUTLINE),
            'resolved_shape_width_cm' => (float) $node->getAttribute(self::ATTR_SHAPE_W),
            'resolved_shape_height_cm' => (float) $node->getAttribute(self::ATTR_SHAPE_H),
        ], $x, $y, $w, $h);
        if (!$trazado) {
            return;
        }

        // Franjas del borde de la caja (mismo dibujo que un border CSS
        // de border-box, sin esquinas: la forma custom no usa radio).
        $b = min($b, $w / 2, $h / 2);
        $franjas = [
            [$x, $y, $w, $b],
            [$x, $y + $h - $b, $w, $b],
            [$x, $y + $b, $b, $h - 2 * $b],
            [$x + $w - $b, $y + $b, $b, $h - 2 * $b],
        ];

        // Un recorte por subtrazado (clipping_polygon acepta uno solo).
        foreach (self::poligonos($trazado) as $poligono) {
            $canvas->clipping_polygon($poligono);
            foreach ($franjas as [$fx, $fy, $fw, $fh]) {
                if ($fw > 0 && $fh > 0) {
                    $canvas->filled_rectangle($fx, $fy, $fw, $fh, $color);
                }
            }
            $canvas->clipping_end();
        }
    }

    /** Segmentos M/L/Z → listas planas [x1, y1, x2, y2, ...] por subtrazado. */
    private static function poligonos(array $trazado): array
    {
        $poligonos = [];
        $actual = [];
        foreach ($trazado as $seg) {
            if ($seg[0] === 'M' && $actual) {
                $poligonos[] = $actual;
                $actual = [];
            }
            if ($seg[0] === 'M' || $seg[0] === 'L') {
                $actual[] = $seg[1];
                $actual[] = $seg[2];
            }
        }
        if ($actual) {
            $poligonos[] = $actual;
        }

        return array_values(array_filter($poligonos, fn($p) => count($p) >= 6));
    }
}
