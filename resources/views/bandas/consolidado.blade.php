<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style type="text/css">
        @font-face {
            font-family: 'QuicksandBook';
            font-style: normal;
            font-weight: 400;
            src: url('{{ public_path("fonts/QuicksandBook-Regular.ttf") }}') format('truetype');
        }

        body {
            margin: 0;
            padding: 0;
        }

        @page {
            margin-left: 1.0cm;
            margin-right: 1.5cm;
            margin-top: 0.4cm;
            margin-bottom: 0.2cm;
        }

        .page {
            padding-left: 5px;
            width: 18.5cm;
            height: 29cm;
            page-break-after: always;
            position: relative;
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* Color en esquina superior izquierda */
        .color-header {
            padding: 0.15cm 0.4cm;
            font-family: 'QuicksandBook', sans-serif;
            font-weight: 700;
            font-size: 10pt;
            color: white;
            display: inline-block;
            margin-bottom: 0.3cm;
        }

        .color-FUCSIA { background-color: #E91E9B; }
        .color-CELESTE { background-color: #46B8DA; }
        .color-TURQUESA { background-color: #00BFA5; }
        .color-ROSA { background-color: #FFB6C1; color: #333; }
        .color-AZUL { background-color: #1E3A8A; }
        .color-VIOLETA { background-color: #9C27B0; }

        .bandas-container {
            width: 100%;
            height: 26cm;
            column-count: 4;
            column-gap: 0.3cm;
            column-fill: auto;
        }

        /* Area de cada banda: 4.5cm x 3.5cm */
        .banda-item {
            width: 4.5cm;
            height: 3.5cm;
            display: block;
            text-align: center;
            margin: 0 0 0.2cm 0;
            break-inside: avoid;
        }

        .banda-content {
            width: 4.5cm;
            height: 3.5cm;
            display: table;
        }

        .banda-inner {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            height: 3.5cm;
        }

        /* Tabla para mantener nombre e icono en la misma linea */
        .nombre-icono-row {
            display: inline-table;
        }

        .nombre-icono-row td {
            vertical-align: middle;
        }

        .banda-nombre {
            font-family: 'QuicksandBook', sans-serif;
            font-weight: normal;
            color: #000;
            text-transform: uppercase;
        }

        .banda-icon {
            padding-left: 0.1cm;
        }

        /* Tamanios de fuente dinamicos segun longitud */
        .font-xxl { font-size: 29pt; }
        .font-xl { font-size: 24pt; }
        .font-lg { font-size: 20pt; }
        .font-md { font-size: 18pt; }
        .font-sm { font-size: 16pt; }
        .font-xs { font-size: 13pt; }

        /* Tamanios de icono */
        .icon-xxl img { height: 24pt; width: auto; }
        .icon-xl img { height: 21pt; width: auto; }
        .icon-lg img { height: 18pt; width: auto; }
        .icon-md img { height: 15pt; width: auto; }
        .icon-sm img { height: 12pt; width: auto; }
        .icon-xs img { height: 10pt; width: auto; }
    </style>
</head>

<body>
    @foreach ($bandasPorColor as $colorNombre => $bandas)
        <div class="page">
            <div class="color-header color-{{ $colorNombre }}">
                {{ $colorNombre }}
            </div>

            <div class="bandas-container">
                @foreach ($bandas as $banda)
                    @php
                        $nombre = $banda['nombre'] ?? '';
                        $icono = $banda['icono'] ?? null;

                        $nameLength = mb_strlen($nombre, 'UTF-8');

                        // Determinar clase de fuente segun longitud
                        if ($nameLength <= 5) {
                            $fontClass = 'font-xxl';
                        } elseif ($nameLength <= 6) {
                            $fontClass = 'font-xl';
                        } elseif ($nameLength <= 7) {
                            $fontClass = 'font-lg';
                        } elseif ($nameLength <= 9) {
                            $fontClass = 'font-md';
                        } elseif ($nameLength <= 11) {
                            $fontClass = 'font-sm';
                        } else {
                            $fontClass = 'font-xs';
                        }
                    @endphp

                    <div class="banda-item">
                        <div class="banda-content">
                            <div class="banda-inner">
                                <table class="nombre-icono-row"><tr>
                                    <td class="banda-nombre {{ $fontClass }}">{{ mb_strtoupper($nombre) }}</td>
                                    @if ($icono)
                                        <td class="banda-icon icon-{{ substr($fontClass, 5) }}"><img src="{{ public_path($icono) }}" alt=""></td>
                                    @endif
                                </tr></table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</body>

</html>
