<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style type="text/css">
        @font-face {
            font-family: 'Oswald';
            font-style: normal;
            font-weight: 400;
            src: url('{{ public_path("fonts/Oswald-Regular.ttf") }}') format('truetype');
        }

        @font-face {
            font-family: 'Oswald';
            font-style: normal;
            font-weight: 700;
            src: url('{{ public_path("fonts/Oswald-Bold.ttf") }}') format('truetype');
        }

        body {
            margin: 0;
            padding: 0;
        }

        @page {
            margin: 0.5cm;
        }

        .hoja {
            padding-left: 5px;
            width: 18.5cm;
        }

        /* ---------- CONTENEDOR GENERAL ---------- */
        .sellos-container {
            width: 100%;
            column-count: 2;
            column-gap: 0.5cm;
            column-fill: auto;
        }

        /* ---------- WRAPPER SELLO + CANTIDAD ---------- */
        .sello-wrapper {
            display: table;
            margin: 10px;
            break-inside: avoid;
        }

        .sello-wrapper-inner {
            display: table-row;
        }

        .sello-cell {
            display: table-cell;
            vertical-align: middle;
        }

        /* ---------- SELLO ---------- */
        .sello-box {
            border: 1px solid #000;
            width: 3.8cm;
            height: 1.4cm;
            position: relative;
        }

        /* ---------- CENTRADO VERTICAL REAL ---------- */
        .sello-content {
            position: absolute;
            top: 50%;
            left: 0;
            width: 100%;
            transform: translateY(-50%);
        }

        /* ---------- CENTRADO HORIZONTAL ---------- */
        .sello-content-inner {
            text-align: center;
        }

        /* ---------- ICONO + TEXTO ---------- */
        .icono-nombre-table {
            display: inline-table;
            margin: 0 auto;
            border-collapse: collapse;
        }

        .icono-nombre-table td {
            vertical-align: middle;
            padding: 0;
        }

        .sello-icono {
            padding-right: 0.1cm !important;
        }

        .sello-icono img {
            height: 0.9cm;
            width: auto;
        }

        /* ---------- TEXTO ---------- */
        .sello-nombre {
            font-family: 'Oswald', sans-serif;
            font-weight: 400;
            color: #000;
            text-transform: uppercase;
            text-align: left;
            line-height: 0.8;
            white-space: normal;
        }

        /* ---------- TAMAÑOS DE FUENTE ---------- */
        .font-xxl {
            font-size: 11pt;
        }

        .font-xl {
            font-size: 11pt;
        }

        .font-lg {
            font-size: 11pt;
        }

        .font-md {
            font-size: 11pt;
        }

        .font-sm {
            font-size: 11pt;
        }

        .font-xs {
            font-size: 11pt;
        }

        /* ---------- CANTIDAD EXTERNA ---------- */
        .cantidad-externa {
            font-family: 'Oswald', sans-serif;
            font-size: 12pt;
            font-weight: 700;
            color: #000;
            padding-left: 0.3cm;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <div class="hoja">
        <div class="sellos-container">
            @foreach ($sellos as $sello)
                @php
                    $nombre = $sello['nombre'] ?? '';
                    $cantidad = $sello['cantidad'] ?? 1;
                    $icono = $sello['icono'] ?? null;

                    $nameLength = mb_strlen($nombre, 'UTF-8');

                    // Determinar clase de fuente segun longitud
                    if ($nameLength <= 6) {
                        $fontClass = 'font-xxl';
                    } elseif ($nameLength <= 10) {
                        $fontClass = 'font-xl';
                    } elseif ($nameLength <= 14) {
                        $fontClass = 'font-lg';
                    } elseif ($nameLength <= 18) {
                        $fontClass = 'font-md';
                    } elseif ($nameLength <= 24) {
                        $fontClass = 'font-sm';
                    } else {
                        $fontClass = 'font-xs';
                    }

                    $tieneIcono = $icono !== null;
                @endphp

                <div class="sello-wrapper">
                    <div class="sello-wrapper-inner">
                        <div class="sello-cell">
                            <div class="sello-box">
                                <div class="sello-content">
                                    <div class="sello-content-inner">
                                        <table class="icono-nombre-table">
                                            <tr>
                                                @if ($tieneIcono)
                                                    <td class="sello-icono"><img src="{{ public_path($icono) }}" alt=""></td>
                                                @endif
                                                @php
                                                    $nombreMayus = mb_strtoupper($nombre, 'UTF-8');
                                                    $palabras = preg_split('/\s+/', trim($nombreMayus));

                                                    if (count($palabras) > 1) {
                                                        $totalChars = mb_strlen(str_replace(' ', '', $nombreMayus), 'UTF-8');
                                                        $target = ceil($totalChars / 2);

                                                        // Encontrar el mejor punto de corte manteniendo el orden original
                                                        $mejorCorte = 1; // al menos 1 palabra en la primera linea
                                                        $mejorDiff = PHP_INT_MAX;

                                                        $charsAcumulados = 0;
                                                        for ($i = 0; $i < count($palabras); $i++) {
                                                            $charsAcumulados += mb_strlen($palabras[$i], 'UTF-8');

                                                            if ($i < count($palabras) - 1) { // no poner todo en linea1
                                                                $diff = abs($charsAcumulados - ($totalChars - $charsAcumulados));
                                                                if ($diff < $mejorDiff) {
                                                                    $mejorDiff = $diff;
                                                                    $mejorCorte = $i + 1;
                                                                }
                                                            }
                                                        }

                                                        $linea1 = implode(' ', array_slice($palabras, 0, $mejorCorte));
                                                        $linea2 = implode(' ', array_slice($palabras, $mejorCorte));

                                                        $nombreRender = $linea1 . '<br>' . $linea2;
                                                    } else {
                                                        $nombreRender = $nombreMayus;
                                                    }
                                                @endphp

                                                <td class="sello-nombre {{ $fontClass }}">{!! $nombreRender !!}</td>

                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if ($cantidad > 1)
                            <div class="sello-cell">
                                <span class="cantidad-externa">x{{ $cantidad }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>

</html>