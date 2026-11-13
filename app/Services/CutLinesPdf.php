<?php

namespace App\Services;

use FPDF;

/**
 * PDF solo con las líneas de corte (sin arte): los trazos de CutLineGeometry,
 * vectoriales, sin relleno y en un spot color "CutContour" (lo que leen los
 * plotters/RIP de la imprenta). Una página por hoja, con el tamaño exacto de
 * la hoja, para que se pueda superponer al PDF de arte. Unidad: pt.
 */
class CutLinesPdf extends FPDF
{
    public const SPOT_NAME = 'CutContour';
    private const CM_TO_PT = 72 / 2.54;

    // clave de color => ['name' => 'CS1', 'space' => ..., 'values' => ..., 'n' => obj]
    private array $spotColors = [];

    public function __construct()
    {
        parent::__construct('P', 'pt');
        $this->SetAutoPageBreak(false);
        $this->SetMargins(0, 0, 0);
    }

    /**
     * Agrega una hoja de $widthCm × $heightCm con sus líneas.
     * $cutConfig null = hoja sin líneas (queda en blanco).
     */
    public function addPaginaCorte(float $widthCm, float $heightCm, ?array $cutConfig, array $trazados): void
    {
        $w = $widthCm * self::CM_TO_PT;
        $h = $heightCm * self::CM_TO_PT;
        $this->AddPage($w > $h ? 'L' : 'P', [$w, $h]);

        if (!$cutConfig || !$trazados) {
            return;
        }

        $cs = $this->spotColor($cutConfig['color']);
        $this->_out('q');
        $this->_out('/' . $cs . ' CS 1 SCN');
        $this->_out(sprintf('%.4F w', $cutConfig['width_cm'] * self::CM_TO_PT));
        foreach ($trazados as $trazado) {
            $this->_out($this->pathOps($trazado) . ' S');
        }
        $this->_out('Q');
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

        if ($this->spotColors) {
            $this->_put('/ColorSpace <<');
            foreach ($this->spotColors as $spot) {
                $this->_put('/' . $spot['name'] . ' ' . $spot['n'] . ' 0 R');
            }
            $this->_put('>>');
        }
    }
}
