<!DOCTYPE html>
<html lang="en">

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

        .hoja {
            padding-left: 5px;
            width: 18.5cm;
            height: 29cm;
        }

        .columna {
            float: left;
            width: 6.5cm;
        }

        .etiqueta-row {
            margin-bottom: 5px;
            display: table;
            width: 100%;
        }

        .icon-cuadro {
            border: solid 1px;
            width: 6cm;
            height: 1cm;
            vertical-align: middle;
            display: table-cell;
            white-space: nowrap;
            line-height: 1cm;
            text-align: center;
        }

        /* Etiqueta grande para cintas pegar (6x2.5cm) */
        .icon-cuadro.grande {
            height: 2.5cm;
            line-height: 2.5cm;
        }

        .cuadro {
            display: inline-block;
            vertical-align: middle;
            line-height: 1cm;
        }

        .cuadro.grande {
            line-height: 2.5cm;
        }

        .imagen {
            display: inline-block;
            vertical-align: middle;
            line-height: 1cm;
            text-align: center;
        }

        .imagen.grande {
            line-height: 2.5cm;
        }

        .personaje {
            max-height: 0.8cm;
            margin-top: 7px;
            margin-right: 7px;
            vertical-align: middle;
        }

        .personaje.grande {
            max-height: 2cm;
            margin-top: 0;
            margin-right: 10px;
        }

        .texto2 {
            text-align: center;
            color: white;
            margin: 0;
            padding: 0;
            display: inline-block;
            vertical-align: middle;
            font-family: 'Oswald';
            max-width: 4.5cm;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .texto2.con-icono {
            line-height: 12px;
        }

        .texto2.sin-icono {
            padding-top: 5px;
            line-height: 12px;
        }

        /* Texto para etiquetas grandes (18pt) */
        .texto2.grande {
            font-size: 18pt;
            line-height: 1.1;
        }

        .texto2.grande.con-icono {
            line-height: 1.1;
        }

        .texto2.grande.sin-icono {
            padding-top: 0;
            line-height: 1.1;
        }

        .cantidad-label {
            font-family: 'Oswald';
            font-size: 0.8em;
            color: #333;
            padding-left: 3px;
            vertical-align: middle;
            display: table-cell;
            width: 0.5cm;
        }

        @page {
            margin-left: 1.0cm;
            margin-right: 1.5cm;
            margin-top: 0.4cm;
            margin-bottom: 0.2cm;
        }
    </style>

<body>
    <div class="hoja">
        @php
            $etiquetasPorColumna = 19;
            $totalEtiquetas = count($etiquetas);
            $columnas = array_chunk($etiquetas, $etiquetasPorColumna);
        @endphp

        @foreach ($columnas as $columna)
            <div class="columna">
                @foreach ($columna as $etiqueta)
                    @php
                        $nombre = $etiqueta['nombre'] ?? '';
                        $cantidad = $etiqueta['cantidad'] ?? 1;
                        $color = $etiqueta['color'] ?? '#000000';
                        $icono = $etiqueta['icono'] ?? null;
                        $esGrande = $etiqueta['grande'] ?? false;

                        $nameLength = mb_strlen($nombre, 'UTF-8');

                        // Tamaños de fuente diferentes para etiquetas grandes vs normales
                        if ($esGrande) {
                            // Etiqueta grande (6x2.5cm) - tipografía base 18pt
                            $fontsize = '18pt';
                        } else {
                            // Etiqueta normal (6x1cm)
                            if ($nameLength <= 16) {
                                $fontsize = '1.1em';
                            } elseif ($nameLength <= 25) {
                                $fontsize = '0.9em';
                            } else {
                                $fontsize = '0.7em';
                            }
                        }

                        $grandeClass = $esGrande ? 'grande' : '';
                    @endphp

                    <div class="etiqueta-row">
                        <div class="icon-cuadro {{ $grandeClass }}">
                            @if ($icono)
                                <div class="imagen {{ $grandeClass }}">
                                    <img class="personaje {{ $grandeClass }}" src="{{ public_path($icono) }}">
                                </div>
                            @endif
                            <div class="cuadro {{ $grandeClass }}">
                                <p class="texto2 {{ $icono ? 'con-icono' : 'sin-icono' }} {{ $grandeClass }}" style="color:{{ $color }};font-size: {{ $fontsize }};">
                                    {{ mb_strtoupper($nombre) }}
                                </p>
                            </div>
                        </div>
                        <span class="cantidad-label">{{ $cantidad > 1 ? 'x' . $cantidad : '' }}</span>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</body>

</html>
