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
            src: url('file://{{ public_path("fonts/Oswald-Regular.ttf") }}') format('truetype');
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 0.5cm;
        }

        .page {
            width: 100%;
            page-break-after: always;
            position: relative;
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* Color en esquina superior izquierda */
        .color-header {
            padding: 0.15cm 0.4cm;
            font-family: 'Oswald', sans-serif;
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
        }

        /* Area de cada banda: 4.5cm x 3.5cm */
        .banda-item {
            width: 4.5cm;
            height: 3.5cm;
            display: inline-block;
            vertical-align: top;
            text-align: center;
            margin-right: 0.3cm;
            margin-bottom: 0.3cm;
            position: relative;
        }

        .banda-content {
            width: 100%;
            height: 100%;
            display: table;
        }

        .banda-inner {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }

        /* Icono al lado del nombre */
        .banda-icon {
            display: inline-block;
            vertical-align: middle;
            margin-right: 0.2cm;
        }

        .banda-icon img {
            max-height: 1.5cm;
            max-width: 1.5cm;
        }

        .banda-nombre {
            font-family: 'Oswald', sans-serif;
            font-weight: 400;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
            vertical-align: middle;
        }

        /* Tamanios de fuente dinamicos segun longitud */
        .font-xxl { font-size: 28pt; }
        .font-xl { font-size: 24pt; }
        .font-lg { font-size: 20pt; }
        .font-md { font-size: 16pt; }
        .font-sm { font-size: 13pt; }
        .font-xs { font-size: 11pt; }
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
                        } elseif ($nameLength <= 7) {
                            $fontClass = 'font-xl';
                        } elseif ($nameLength <= 9) {
                            $fontClass = 'font-lg';
                        } elseif ($nameLength <= 12) {
                            $fontClass = 'font-md';
                        } elseif ($nameLength <= 15) {
                            $fontClass = 'font-sm';
                        } else {
                            $fontClass = 'font-xs';
                        }
                    @endphp

                    <div class="banda-item">
                        <div class="banda-content">
                            <div class="banda-inner">
                                @if ($icono)
                                    <span class="banda-icon">
                                        <img src="{{ public_path($icono) }}" alt="">
                                    </span>
                                @endif
                                <span class="banda-nombre {{ $fontClass }}">{{ mb_strtoupper($nombre) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</body>

</html>
