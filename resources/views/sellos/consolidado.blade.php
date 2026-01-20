<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css"
        href="https://fonts.googleapis.com/css?family=Lora:400,700,400italic|Oswald:400,700" media="screen">
    <style type="text/css">
        @font-face {
            font-family: 'Oswald';
            font-style: normal;
            font-weight: 400;
            src: url('file://{{ public_path("fonts/Oswald-Regular.ttf") }}') format('truetype');
        }

        @font-face {
            font-family: 'Oswald';
            font-style: normal;
            font-weight: 700;
            src: url('file://{{ public_path("fonts/Oswald-Bold.ttf") }}') format('truetype');
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 0.8cm;
        }

        .hoja {
            width: 100%;
        }

        .sellos-container {
            width: 100%;
        }

        .sello-row {
            margin-bottom: 12px;
            display: block;
            width: 100%;
        }

        /* Sello individual: 3.8cm x 1.4cm segun instrucciones */
        .sello-box {
            border: solid 1px #000;
            width: 3.8cm;
            height: 1.4cm;
            display: inline-block;
            vertical-align: top;
            margin-right: 8px;
            margin-bottom: 8px;
            position: relative;
        }

        .sello-content {
            display: table;
            width: 100%;
            height: 100%;
        }

        .sello-content-inner {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding: 2px 4px;
        }

        /* Sello CON icono: icono a la izquierda, nombre a la derecha */
        .sello-con-icono .sello-content-inner {
            text-align: left;
            padding-left: 6px;
        }

        .sello-icono {
            display: inline-block;
            vertical-align: middle;
            width: 1cm;
            height: 1cm;
            margin-right: 4px;
        }

        .sello-icono img {
            max-width: 1cm;
            max-height: 1cm;
            vertical-align: middle;
        }

        .sello-nombre-container {
            display: inline-block;
            vertical-align: middle;
            max-width: 2.4cm;
        }

        /* Sello SIN icono: nombre centrado */
        .sello-sin-icono .sello-nombre-container {
            max-width: 3.4cm;
        }

        .sello-nombre {
            font-family: 'Oswald', sans-serif;
            font-weight: 700;
            color: #000;
            text-transform: uppercase;
            line-height: 1.1;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Tamanios de fuente segun longitud */
        .font-lg { font-size: 11pt; }
        .font-md { font-size: 9pt; }
        .font-sm { font-size: 7pt; }
        .font-xs { font-size: 6pt; }

        .cantidad-badge {
            font-family: 'Oswald', sans-serif;
            font-size: 7pt;
            color: #666;
            position: absolute;
            bottom: 1px;
            right: 3px;
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
                    if ($nameLength <= 12) {
                        $fontClass = 'font-lg';
                    } elseif ($nameLength <= 18) {
                        $fontClass = 'font-md';
                    } elseif ($nameLength <= 25) {
                        $fontClass = 'font-sm';
                    } else {
                        $fontClass = 'font-xs';
                    }

                    $tieneIcono = $icono !== null;
                @endphp

                <div class="sello-box {{ $tieneIcono ? 'sello-con-icono' : 'sello-sin-icono' }}">
                    <div class="sello-content">
                        <div class="sello-content-inner">
                            @if ($tieneIcono)
                                <div class="sello-icono">
                                    <img src="{{ public_path($icono) }}" alt="">
                                </div>
                            @endif
                            <div class="sello-nombre-container">
                                <span class="sello-nombre {{ $fontClass }}">{{ mb_strtoupper($nombre) }}</span>
                            </div>
                        </div>
                    </div>
                    @if ($cantidad > 1)
                        <span class="cantidad-badge">x{{ $cantidad }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</body>

</html>
