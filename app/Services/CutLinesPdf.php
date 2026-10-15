<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

/**
 * PDF con 2 capas (OCG): "Arte" (las páginas que ya renderizó dompdf,
 * importadas con FPDI como vectores) y "Líneas de corte" encima, con los
 * trazos de CutLineGeometry en un spot color "CutContour" (lo que leen los
 * plotters/RIP de la imprenta). Las 2 capas existen siempre, aunque la de
 * corte quede vacía. Unidad: pt.
 */
class CutLinesPdf extends Fpdi
{
    public const LAYER_ART = 'Arte';
    public const LAYER_CUT = 'Líneas de corte';
    public const SPOT_NAME = 'CutContour';
    private const CM_TO_PT = 72 / 2.54;

    private array $layers = [
        'OC1' => ['name' => self::LAYER_ART, 'n' => null],
        'OC2' => ['name' => self::LAYER_CUT, 'n' => null],
    ];
    // clave de color => ['name' => 'CS1', 'space' => ..., 'values' => ..., 'n' => obj]
    private array $spotColors = [];

    public function __construct()
    {
        parent::__construct('P', 'pt');
        $this->SetAutoPageBreak(false);
        $this->SetMargins(0, 0, 0);
        // OCG = PDF 1.5. FPDI lo sube solo si el PDF importado pide más.
        $this->PDFVersion = '1.5';
    }

    /**
     * Agrega una página de arte (importada) y encima su capa de corte.
     * $cutConfig null = página sin líneas (la capa igual se marca, vacía).
     */
    public function addPaginaConCorte(string $tplId, ?array $cutConfig, array $trazados): void
    {
        $size = $this->getTemplateSize($tplId);
        $this->AddPage($size['orientation'], [$size['width'], $size['height']]);

        $this->_out('/OC /OC1 BDC');
        $this->useTemplate($tplId);
        $this->_out('EMC');

        $this->_out('/OC /OC2 BDC');
        if ($cutConfig && $trazados) {
            $cs = $this->spotColor($cutConfig['color']);
            $this->_out('q');
            $this->_out('/' . $cs . ' CS 1 SCN');
            $this->_out(sprintf('%.4F w', $cutConfig['width_cm'] * self::CM_TO_PT));
            foreach ($trazados as $trazado) {
                $this->_out($this->pathOps($trazado) . ' S');
            }
            $this->_out('Q');
        }
        $this->_out('EMC');
    }

    private function spotColor(array $color): string
    {
        $key = $color['space'] . ':' . implode(',', $color['values']);
        if (!isset($this->spotColors[$key])) {
            $this->spotColors[$key] = $color + ['name' => 'CS' . (count($this->spotColors) + 1), 'n' => null];
        }
        return $this->spotColors[$key]['name'];
    }

    /** Segmentos en cm (Y hacia abajo) → operadores PDF en pt (Y hacia arriba). */
    private function pathOps(array $trazado): string
    {
        $pt = fn($x, $y) => sprintf('%.3F %.3F', $x * self::CM_TO_PT, $this->h - $y * self::CM_TO_PT);
        $ops = [];
        foreach ($trazado as $seg) {
            switch ($seg[0]) {
                case 'M':
                    $ops[] = $pt($seg[1], $seg[2]) . ' m';
                    break;
                case 'L':
                    $ops[] = $pt($seg[1], $seg[2]) . ' l';
                    break;
                case 'C':
                    $ops[] = $pt($seg[1], $seg[2]) . ' ' . $pt($seg[3], $seg[4]) . ' ' . $pt($seg[5], $seg[6]) . ' c';
                    break;
                case 'Z':
                    $ops[] = 'h';
                    break;
            }
        }
        return implode(' ', $ops);
    }

    protected function _putresources()
    {
        foreach ($this->layers as $id => $layer) {
            $this->_newobj();
            $this->layers[$id]['n'] = $this->n;
            $this->_put('<</Type /OCG /Name ' . $this->_textstring($layer['name']) . '>>');
            $this->_put('endobj');
        }

        foreach ($this->spotColors as $key => $spot) {
            $alt = $spot['space'] === 'cmyk' ? '/DeviceCMYK' : '/DeviceRGB';
            $c0 = $spot['space'] === 'cmyk' ? '[0 0 0 0]' : '[1 1 1]';
            $c1 = '[' . implode(' ', array_map(fn($v) => sprintf('%.4F', $v), $spot['values'])) . ']';
            $this->_newobj();
            $this->spotColors[$key]['n'] = $this->n;
            $this->_put('[/Separation /' . self::SPOT_NAME . ' ' . $alt
                . ' <</FunctionType 2 /Domain [0 1] /C0 ' . $c0 . ' /C1 ' . $c1 . ' /N 1>>]');
            $this->_put('endobj');
        }

        parent::_putresources();
    }

    protected function _putresourcedict()
    {
        parent::_putresourcedict();

        $this->_put('/Properties <<');
        foreach ($this->layers as $id => $layer) {
            $this->_put('/' . $id . ' ' . $layer['n'] . ' 0 R');
        }
        $this->_put('>>');

        if ($this->spotColors) {
            $this->_put('/ColorSpace <<');
            foreach ($this->spotColors as $spot) {
                $this->_put('/' . $spot['name'] . ' ' . $spot['n'] . ' 0 R');
            }
            $this->_put('>>');
        }
    }

    protected function _putcatalog()
    {
        parent::_putcatalog();

        $refs = array_map(fn($l) => $l['n'] . ' 0 R', $this->layers);
        $all = implode(' ', $refs);
        // /Order de arriba hacia abajo en el panel de capas: corte primero.
        $order = $refs['OC2'] . ' ' . $refs['OC1'];
        $this->_put('/OCProperties <</OCGs [' . $all . '] /D <</Order [' . $order . '] /ON [' . $all . '] /OFF []>>>>');
    }
}
