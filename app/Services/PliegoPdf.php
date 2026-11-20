<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

/**
 * Fpdi (unidad mm) con:
 *  - recorte rectangular (clipping): el pliego coloca cada hoja en su lugar
 *    recortándole los márgenes laterales; sin clip la parte que sobra
 *    pisaría el lugar de al lado.
 *  - trazos de líneas de corte en spot color "CutContour" (mismo criterio
 *    que CutLinesPdf del editor de PDFs).
 */
class PliegoPdf extends Fpdi
{
    // clave de color => ['name' => 'CS1', 'space' => ..., 'values' => ..., 'n' => obj]
    private array $spotColors = [];

    public function startClip(float $x, float $y, float $w, float $h): void
    {
        $this->_out(sprintf(
            'q %.3F %.3F %.3F %.3F re W n',
            $x * $this->k,
            ($this->h - $y) * $this->k,
            $w * $this->k,
            -$h * $this->k
        ));
    }

    public function endClip(): void
    {
        $this->_out('Q');
    }

    /**
     * Dibuja trazados (segmentos en mm del pliego, Y hacia abajo:
     * ['M', x, y] | ['L', x, y] | ['C', x1, y1, x2, y2, x, y] | ['Z'])
     * con el spot color CutContour. $color: ['space' => 'rgb'|'cmyk', 'values' => [...]].
     */
    public function cutPaths(array $trazados, array $color, float $widthMm): void
    {
        if (!$trazados) {
            return;
        }
        $cs = $this->spotColor($color);
        $this->_out('q');
        $this->_out('/' . $cs . ' CS 1 SCN');
        $this->_out(sprintf('%.4F w', $widthMm * $this->k));
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

    private function pathOps(array $trazado): string
    {
        $pt = fn($x, $y) => sprintf('%.3F %.3F', $x * $this->k, ($this->h - $y) * $this->k);
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
            $this->_put('[/Separation /' . CutLinesPdf::SPOT_NAME . ' ' . $alt
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
