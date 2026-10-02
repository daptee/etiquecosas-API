<?php

namespace App\Services;

/**
 * Intérprete mínimo de un content stream de PDF — pensado específicamente
 * para los PDF que genera ESTE backend (dompdf, `enable_font_subsetting:
 * false`), no para PDF arbitrarios de cualquier origen. Por eso puede asumir
 * cosas que en general NO son ciertas de cualquier PDF:
 *
 * - El texto está en WinAnsiEncoding/Windows-1252 de un solo byte por
 *   carácter (cierto sin subsetting, para texto latino simple como nombres) —
 *   no hace falta resolver /ToUnicode ni el diccionario de fuentes.
 * - No hace falta el árbol de objetos del PDF ni /Resources — todo lo que
 *   necesitamos (tamaño de fuente, color, posición) ya viene como operando
 *   directo en el content stream.
 *
 * Devuelve una lista plana de "primitivas" ya en coordenadas ABSOLUTAS de
 * página, en puntos PDF (origen abajo-izquierda, Y hacia arriba — la
 * conversión a cm/Y-hacia-abajo la hace quien use esto, no este parser).
 */
class PdfContentStreamParser
{
    /**
     * @return array<int, array> Lista de ['type' => 'rect'|'image'|'text', ...]
     */
    public static function parse(string $content): array
    {
        $primitives = [];

        // Pila de graphic states: cada uno con su propia CTM (matriz 2x3
        // [a,b,c,d,e,f]) y color de relleno vigente. q/Q empuja/saca de acá.
        $identity = [1, 0, 0, 1, 0, 0];
        $stack = [];
        $ctm = $identity;
        $fillColor = '#000000';
        $strokeColor = '#000000';
        $lineWidthPt = 1.0;

        // Texto: matriz de línea (Tlm) y matriz de texto (Tm), ambas se
        // resetean a identidad en BT. Td/TD las mueven, Tm las reemplaza.
        $inText = false;
        $tlm = $identity;
        $tm = $identity;
        $fontSizePt = 12.0;

        // Path pendiente (re, o una secuencia m/l/c) — se resuelve a un
        // rectángulo (bounding box) recién cuando aparece f/f*/S.
        $pathPoints = [];

        $tokens = self::tokenize($content);
        $operands = [];

        foreach ($tokens as $token) {
            if (!self::isOperator($token)) {
                $operands[] = $token;
                continue;
            }

            $op = $token;

            switch ($op) {
                case 'q':
                    $stack[] = ['ctm' => $ctm, 'color' => $fillColor, 'stroke' => $strokeColor, 'lw' => $lineWidthPt];
                    break;

                case 'Q':
                    $prev = array_pop($stack);
                    if ($prev) {
                        $ctm = $prev['ctm'];
                        $fillColor = $prev['color'];
                        $strokeColor = $prev['stroke'];
                        $lineWidthPt = $prev['lw'];
                    }
                    break;

                case 'cm':
                    if (count($operands) >= 6) {
                        $m = array_map('floatval', array_slice($operands, -6));
                        $ctm = self::multiply($m, $ctm);
                    }
                    break;

                case 'rg':
                    if (count($operands) >= 3) {
                        [$r, $g, $b] = array_map('floatval', array_slice($operands, -3));
                        $fillColor = self::rgbToHex($r, $g, $b);
                    }
                    break;

                case 'g':
                    if (count($operands) >= 1) {
                        $gray = (float) end($operands);
                        $fillColor = self::rgbToHex($gray, $gray, $gray);
                    }
                    break;

                case 'k':
                    if (count($operands) >= 4) {
                        [$c, $m2, $y, $k] = array_map('floatval', array_slice($operands, -4));
                        $fillColor = self::cmykToHex($c, $m2, $y, $k);
                    }
                    break;

                case 'RG':
                    if (count($operands) >= 3) {
                        [$r, $g, $b] = array_map('floatval', array_slice($operands, -3));
                        $strokeColor = self::rgbToHex($r, $g, $b);
                    }
                    break;

                case 'G':
                    if (count($operands) >= 1) {
                        $gray = (float) end($operands);
                        $strokeColor = self::rgbToHex($gray, $gray, $gray);
                    }
                    break;

                case 'K':
                    if (count($operands) >= 4) {
                        [$c, $m2, $y, $k] = array_map('floatval', array_slice($operands, -4));
                        $strokeColor = self::cmykToHex($c, $m2, $y, $k);
                    }
                    break;

                case 'w':
                    if (count($operands) >= 1) {
                        $lineWidthPt = (float) end($operands);
                    }
                    break;

                case 're':
                    if (count($operands) >= 4) {
                        [$x, $y, $w, $h] = array_map('floatval', array_slice($operands, -4));
                        // Las 4 esquinas, transformadas por la CTM vigente.
                        $pathPoints[] = self::applyMatrix($ctm, $x, $y);
                        $pathPoints[] = self::applyMatrix($ctm, $x + $w, $y);
                        $pathPoints[] = self::applyMatrix($ctm, $x + $w, $y + $h);
                        $pathPoints[] = self::applyMatrix($ctm, $x, $y + $h);
                    }
                    break;

                case 'm':
                case 'l':
                    if (count($operands) >= 2) {
                        [$x, $y] = array_map('floatval', array_slice($operands, -2));
                        $pathPoints[] = self::applyMatrix($ctm, $x, $y);
                    }
                    break;

                case 'c':
                case 'v':
                case 'y':
                    // Curva Bézier: para el propósito de "bounding box", con
                    // los puntos de control alcanza (no hace falta trazar la
                    // curva real).
                    if (count($operands) >= 2) {
                        $pairs = array_chunk(array_map('floatval', $operands), 2);
                        foreach ($pairs as $pair) {
                            if (count($pair) === 2) {
                                $pathPoints[] = self::applyMatrix($ctm, $pair[0], $pair[1]);
                            }
                        }
                    }
                    break;

                case 'f':
                case 'F':
                case 'f*':
                case 'b':
                case 'b*':
                case 'B':
                case 'B*':
                    if (count($pathPoints) >= 2) {
                        $box = self::boundingBox($pathPoints);
                        if ($box['w'] > 0.5 && $box['h'] > 0.5) { // ignora líneas/puntos degenerados
                            $primitives[] = [
                                'type' => 'rect',
                                'x' => $box['x'], 'y' => $box['y'], 'w' => $box['w'], 'h' => $box['h'],
                                'color' => $fillColor,
                                'filled' => true,
                                'borderColor' => in_array($op, ['b', 'b*', 'B', 'B*'], true) ? $strokeColor : null,
                                'borderWidthPt' => in_array($op, ['b', 'b*', 'B', 'B*'], true) ? $lineWidthPt : 0,
                            ];
                        }
                    }
                    $pathPoints = [];
                    break;

                case 'S':
                case 's':
                    // Sin relleno, solo contorno — igual sirve como "celda" de
                    // etiqueta para agrupar texto/ícono (ver PdfDesignImportService).
                    if (count($pathPoints) >= 2) {
                        $box = self::boundingBox($pathPoints);
                        if ($box['w'] > 0.5 && $box['h'] > 0.5) {
                            $primitives[] = [
                                'type' => 'rect',
                                'x' => $box['x'], 'y' => $box['y'], 'w' => $box['w'], 'h' => $box['h'],
                                'color' => '#FFFFFF',
                                'filled' => false,
                                'borderColor' => $strokeColor,
                                'borderWidthPt' => $lineWidthPt,
                            ];
                        }
                    }
                    $pathPoints = [];
                    break;

                case 'n':
                    $pathPoints = [];
                    break;

                case 'BT':
                    $inText = true;
                    $tlm = $identity;
                    $tm = $identity;
                    break;

                case 'ET':
                    $inText = false;
                    break;

                case 'Tf':
                    if (count($operands) >= 2) {
                        $fontSizePt = (float) end($operands);
                    }
                    break;

                case 'Td':
                    if (count($operands) >= 2) {
                        [$tx, $ty] = array_map('floatval', array_slice($operands, -2));
                        $tlm = self::multiply([1, 0, 0, 1, $tx, $ty], $tlm);
                        $tm = $tlm;
                    }
                    break;

                case 'TD':
                    if (count($operands) >= 2) {
                        [$tx, $ty] = array_map('floatval', array_slice($operands, -2));
                        $tlm = self::multiply([1, 0, 0, 1, $tx, $ty], $tlm);
                        $tm = $tlm;
                    }
                    break;

                case 'Tm':
                    if (count($operands) >= 6) {
                        $tm = array_map('floatval', array_slice($operands, -6));
                        $tlm = $tm;
                    }
                    break;

                case "T*":
                    // Línea siguiente sin desplazamiento explícito — no lo
                    // necesitamos (los textos que nos interesan usan Td).
                    break;

                case 'Tj':
                case "'":
                case '"':
                    if (!empty($operands)) {
                        $raw = (string) end($operands);
                        self::emitText($primitives, $raw, $ctm, $tm, $fontSizePt, $fillColor);
                    }
                    break;

                case 'TJ':
                    // El operando es un array PDF "[ (texto) num (texto) ... ]"
                    // ya tokenizado como UN string "literal de array" — se
                    // vuelve a tokenizar acá mismo para sacar solo los strings.
                    if (!empty($operands)) {
                        $arrLiteral = (string) end($operands);
                        foreach (self::extractArrayStrings($arrLiteral) as $raw) {
                            self::emitText($primitives, $raw, $ctm, $tm, $fontSizePt, $fillColor);
                        }
                    }
                    break;

                case 'Do':
                    // Asume el patrón típico de dompdf: un "cm" escala un
                    // cuadrado unitario justo antes de Do, así que la CTM
                    // vigente YA da el rectángulo real donde se colocó la
                    // imagen (sin necesidad de resolver el XObject).
                    $origin = self::applyMatrix($ctm, 0, 0);
                    $corner = self::applyMatrix($ctm, 1, 1);
                    $w = abs($corner[0] - $origin[0]);
                    $h = abs($corner[1] - $origin[1]);
                    if ($w > 0.5 && $h > 0.5) {
                        $primitives[] = [
                            'type' => 'image',
                            'x' => min($origin[0], $corner[0]),
                            'y' => min($origin[1], $corner[1]),
                            'w' => $w, 'h' => $h,
                        ];
                    }
                    break;
            }

            $operands = [];
        }

        return $primitives;
    }

    private static function emitText(array &$primitives, string $raw, array $ctm, array $tm, float $fontSizePt, string $fillColor): void
    {
        $text = self::decodeLiteralString($raw);
        if ($text === '') {
            return;
        }
        // Posición del origen del texto: Tm aplicada sobre el origen (0,0),
        // y después la CTM vigente (el "cm" de más afuera, si lo hay).
        $pt = self::applyMatrix($tm, 0, 0);
        $pt = self::applyMatrix($ctm, $pt[0], $pt[1]);

        $primitives[] = [
            'type' => 'text',
            'x' => $pt[0], 'y' => $pt[1],
            'text' => $text,
            'fontSizePt' => $fontSizePt,
            'color' => $fillColor,
        ];
    }

    /**
     * Separa un content stream en tokens: operandos (números, strings
     * literales "(...)", arrays "[...]") y operadores (todo lo demás).
     */
    private static function tokenize(string $content): array
    {
        $tokens = [];
        $len = strlen($content);
        $i = 0;

        while ($i < $len) {
            $ch = $content[$i];

            if (ctype_space($ch)) {
                $i++;
                continue;
            }

            if ($ch === '%') { // comentario hasta fin de línea
                while ($i < $len && $content[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($ch === '(') {
                // String literal con paréntesis balanceados y \) escapado.
                $depth = 1;
                $start = $i;
                $i++;
                while ($i < $len && $depth > 0) {
                    if ($content[$i] === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($content[$i] === '(') $depth++;
                    if ($content[$i] === ')') $depth--;
                    $i++;
                }
                $tokens[] = substr($content, $start, $i - $start);
                continue;
            }

            if ($ch === '[') {
                $depth = 1;
                $start = $i;
                $i++;
                while ($i < $len && $depth > 0) {
                    if ($content[$i] === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($content[$i] === '[') $depth++;
                    if ($content[$i] === ']') $depth--;
                    $i++;
                }
                $tokens[] = substr($content, $start, $i - $start);
                continue;
            }

            if ($ch === '<') {
                // Hex string <...> o diccionario <<...>> — se descarta el
                // contenido (no lo necesitamos), solo se avanza el cursor.
                $start = $i;
                if (($content[$i + 1] ?? '') === '<') {
                    $i += 2;
                    while ($i < $len - 1 && !($content[$i] === '>' && $content[$i + 1] === '>')) {
                        $i++;
                    }
                    $i += 2;
                } else {
                    $i++;
                    while ($i < $len && $content[$i] !== '>') {
                        $i++;
                    }
                    $i++;
                }
                $tokens[] = substr($content, $start, $i - $start);
                continue;
            }

            if ($ch === '/') {
                // Nombre /Foo — lo tratamos como un operando opaco (no lo usamos para decidir nada).
                $start = $i;
                $i++;
                while ($i < $len && !ctype_space($content[$i]) && !in_array($content[$i], ['/', '(', '[', '<', ']', '>'], true)) {
                    $i++;
                }
                $tokens[] = substr($content, $start, $i - $start);
                continue;
            }

            // Número u operador: ambos son una racha de caracteres "normales".
            $start = $i;
            while (
                $i < $len && !ctype_space($content[$i])
                && !in_array($content[$i], ['(', ')', '[', ']', '<', '>', '/', '%'], true)
            ) {
                $i++;
            }
            if ($i === $start) { // caracter suelto no reconocido, para no colgarse
                $i++;
                continue;
            }
            $tokens[] = substr($content, $start, $i - $start);
        }

        return $tokens;
    }

    private static function isOperator(string $token): bool
    {
        if ($token === '') return false;
        $first = $token[0];
        if ($first === '(' || $first === '[' || $first === '/' || $first === '<') {
            return false;
        }
        // Un número (operando) vs un operador (TJ, Tf, re, cm, etc.)
        return !is_numeric($token);
    }

    private static function decodeLiteralString(string $raw): string
    {
        if ($raw === '' || $raw[0] !== '(') {
            return '';
        }
        $inner = substr($raw, 1, -1);
        // Des-escapa \), \(, \\\\ y las secuencias octales \ddd.
        $inner = preg_replace_callback('/\\\\([()\\\\]|[0-7]{1,3})/', function ($m) {
            $esc = $m[1];
            if (ctype_digit($esc)) {
                return chr(octdec($esc) & 0xFF);
            }
            return $esc;
        }, $inner);

        // WinAnsiEncoding/Windows-1252 de un solo byte por carácter — válido
        // porque este parser asume texto latino simple sin subsetting (ver
        // comentario de la clase).
        $utf8 = @mb_convert_encoding($inner, 'UTF-8', 'Windows-1252');
        return $utf8 !== false ? $utf8 : $inner;
    }

    /**
     * De un literal de array PDF ya tokenizado como string completo
     * "[ (ABC) -50 (DEF) ]", saca solo los sub-strings de texto (ignora los
     * números de kerning).
     */
    private static function extractArrayStrings(string $arrLiteral): array
    {
        // Devuelve los literales CRUDOS "(...)" tal cual — emitText() es
        // quien decodifica (mismo paso único que usa el caso Tj), para no
        // decodificar dos veces (decodificar "JUAN" ya decodificado no
        // empieza con "(" y decodeLiteralString() devuelve vacío).
        if (!preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $arrLiteral, $m)) {
            return [];
        }
        return $m[0];
    }

    private static function multiply(array $m1, array $m2): array
    {
        [$a1, $b1, $c1, $d1, $e1, $f1] = $m1;
        [$a2, $b2, $c2, $d2, $e2, $f2] = $m2;
        return [
            $a1 * $a2 + $b1 * $c2,
            $a1 * $b2 + $b1 * $d2,
            $c1 * $a2 + $d1 * $c2,
            $c1 * $b2 + $d1 * $d2,
            $e1 * $a2 + $f1 * $c2 + $e2,
            $e1 * $b2 + $f1 * $d2 + $f2,
        ];
    }

    private static function applyMatrix(array $m, float $x, float $y): array
    {
        [$a, $b, $c, $d, $e, $f] = $m;
        return [$a * $x + $c * $y + $e, $b * $x + $d * $y + $f];
    }

    private static function boundingBox(array $points): array
    {
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);
        return ['x' => $minX, 'y' => $minY, 'w' => $maxX - $minX, 'h' => $maxY - $minY];
    }

    private static function rgbToHex(float $r, float $g, float $b): string
    {
        $clamp = fn($v) => max(0, min(255, (int) round($v * 255)));
        return sprintf('#%02X%02X%02X', $clamp($r), $clamp($g), $clamp($b));
    }

    private static function cmykToHex(float $c, float $m, float $y, float $k): string
    {
        $clamp = fn($v) => max(0, min(255, (int) round(255 * (1 - $v) * (1 - $k))));
        return sprintf('#%02X%02X%02X', $clamp($c), $clamp($m), $clamp($y));
    }
}
