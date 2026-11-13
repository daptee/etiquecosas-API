<?php

namespace App\Services;

use Barryvdh\DomPDF\PDF;
use Dompdf\Adapter\CPDF as CpdfCanvas;
use Dompdf\Canvas;
use Dompdf\Cpdf as CpdfDocument;
use Dompdf\Css\Color;
use Dompdf\Frame;
use Dompdf\Renderer\Text as TextRenderer;

/**
 * Contorno de letra (`text_stroke`, ver PDF_CONTORNO_LETRA.md) en los textos
 * de RENDER.blade.php.
 *
 * Dompdf no soporta -webkit-text-stroke ni text-shadow, así que el contorno se
 * dibuja con el trazo nativo de texto de PDF (operador `Tr`), inyectado con
 * callbacks de Dompdf alrededor de cada renglón (cada frame de texto). Cpdf no
 * expone `Tr`, por eso se escribe el operador crudo en el content stream.
 *
 * El trazo de PDF cae centrado sobre el borde de la letra, así que se dibuja
 * con el DOBLE del ancho pedido y se esconde la mitad que sobra:
 *
 * - outside: el blade dibuja dos capas iguales del texto. La de abajo lleva el
 *   atributo con "outside" y sale solo con trazo (modo 1); la de arriba es el
 *   texto normal (relleno), que tapa la mitad interior del trazo. Dos capas
 *   (y no trazo+relleno por renglón) para que el trazo de un renglón no pise
 *   el relleno del renglón de arriba.
 * - inside: por renglón, el texto se usa primero como recorte (modo 7) y
 *   después se vuelve a dibujar con relleno + trazo (modo 2) dentro de ese
 *   recorte, que elimina la mitad exterior del trazo. Todo entre q/Q.
 */
class PdfTextStroke
{
    public const ATTR_MODE = 'data-text-stroke';
    public const ATTR_WIDTH = 'data-text-stroke-width-pt';
    public const ATTR_COLOR = 'data-text-stroke-color';
    public const ATTR_JOIN = 'data-text-stroke-join';

    public const MODE_OUTSIDE = 'outside';
    public const MODE_INSIDE = 'inside';

    private const CM_TO_PT = 72 / 2.54;

    /**
     * Registra los callbacks en el Dompdf del PDF (antes de save()/output()).
     */
    public static function registrar(PDF $pdf): PDF
    {
        $pdf->getDomPDF()->setCallbacks([
            ['event' => 'begin_frame', 'f' => [self::class, 'alEmpezarFrame']],
            ['event' => 'end_frame', 'f' => [self::class, 'alTerminarFrame']],
        ]);

        return $pdf;
    }

    /**
     * Datos de dibujo del contorno de un texto ya saneado, o null si no tiene.
     * `color_css` usa el mismo formato que el `color` de la letra en el blade.
     */
    public static function resolver(array $el): ?array
    {
        if (($el['type'] ?? null) !== 'text' || !isset($el['text_stroke'])) {
            return null;
        }

        $stroke = PdfDesignSanitizer::sanitizeTextStroke($el['text_stroke']);
        if ($stroke === null) {
            return null;
        }

        // Color "De la compra" (value_mode dynamic): se usa el respaldo
        // mode/value, igual que hoy con color/border.color/fill.color.
        $color = $stroke['color'];
        $colorCss = $color['mode'] === 'cmyk' ? 'cmyk(' . $color['value'] . ')' : $color['value'];

        return [
            'mode' => $stroke['position'],
            'width_pt' => round(2 * $stroke['width_cm'] * self::CM_TO_PT, 4),
            'color_css' => $colorCss,
            'join' => $stroke['join'],
        ];
    }

    public static function alEmpezarFrame(Frame $frame, Canvas $canvas): void
    {
        $stroke = self::contornoDelFrame($frame, $canvas);
        if ($stroke === null) {
            return;
        }

        $cpdf = $canvas->get_cpdf();
        $cpdf->save();

        if ($stroke['mode'] === self::MODE_OUTSIDE) {
            self::fijarTrazo($cpdf, $stroke);
            self::operador($cpdf, '1 Tr');
        } else {
            // El renderer dibuja el renglón como recorte (no pinta nada).
            self::operador($cpdf, '7 Tr');
        }
    }

    public static function alTerminarFrame(Frame $frame, Canvas $canvas): void
    {
        $stroke = self::contornoDelFrame($frame, $canvas);
        if ($stroke === null) {
            return;
        }

        $cpdf = $canvas->get_cpdf();

        if ($stroke['mode'] === self::MODE_INSIDE) {
            // Segunda pasada del mismo renglón: relleno (color de la letra) +
            // trazo, recortado por la primera.
            self::fijarTrazo($cpdf, $stroke);
            self::operador($cpdf, '2 Tr');
            (new TextRenderer($canvas->get_dompdf()))->render($frame);
        }

        // Q vuelve también el modo de texto (Tr es estado gráfico) y el recorte.
        $cpdf->restore();
    }

    /**
     * Contorno a aplicar a este frame: solo frames de texto con un ancestro
     * marcado por el blade. Mismo resultado en begin_frame y end_frame, así
     * cada q tiene su Q (Renderer::render() corta antes de end_frame en
     * visibility hidden/collapse, por eso esos se saltean).
     */
    private static function contornoDelFrame(Frame $frame, Canvas $canvas): ?array
    {
        if (!$canvas instanceof CpdfCanvas || !$frame->is_text_node()) {
            return null;
        }
        if (in_array($frame->get_style()->visibility, ['hidden', 'collapse'], true)) {
            return null;
        }

        $node = $frame->get_node()->parentNode;
        while ($node instanceof \DOMElement) {
            if ($node->hasAttribute(self::ATTR_MODE)) {
                $mode = $node->getAttribute(self::ATTR_MODE);
                $width = (float) $node->getAttribute(self::ATTR_WIDTH);
                $color = Color::parse($node->getAttribute(self::ATTR_COLOR));
                if (!in_array($mode, [self::MODE_OUTSIDE, self::MODE_INSIDE], true) || $width <= 0 || !is_array($color)) {
                    return null;
                }

                return [
                    'mode' => $mode,
                    'width_pt' => $width,
                    'color' => $color,
                    'join' => $node->getAttribute(self::ATTR_JOIN) === 'miter' ? 'miter' : 'round',
                ];
            }
            $node = $node->parentNode;
        }

        return null;
    }

    private static function fijarTrazo(CpdfDocument $cpdf, array $stroke): void
    {
        $cpdf->setStrokeColor($stroke['color']);
        // round: esquinas y puntas redondeadas (join 1 / cap 1); miter: en
        // punta (join 0 / cap 0).
        $round = $stroke['join'] === 'round';
        $cpdf->setLineStyle($stroke['width_pt'], $round ? 'round' : 'butt', $round ? 'round' : 'miter');
        if (!$round) {
            // Límite de miter 4 (el default de CSS/SVG, lo que usa el editor)
            // en vez del 10 de PDF, que deja picos largos en M, W, V...
            self::operador($cpdf, '4 M');
        }
    }

    /**
     * Escribe un operador crudo en el content stream de la página actual
     * (Cpdf::addContent() es privado y no hay API pública para `Tr`).
     */
    private static function operador(CpdfDocument $cpdf, string $operador): void
    {
        \Closure::bind(function (string $content) {
            $this->addContent($content);
        }, $cpdf, CpdfDocument::class)("\n" . $operador);
    }
}
