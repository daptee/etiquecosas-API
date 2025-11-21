<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css"
        href="https://fonts.googleapis.com/css?family=Lora:400,700,400italic|Oswald:400,700" media="screen">

    <style>
        @font-face {
            font-family: 'Lora';
            font-style: normal;
            font-weight: 400;
            src: url('file://{{ public_path("fonts/Lora-Regular.ttf") }}') format('truetype');
        }

        @font-face {
            font-family: 'Lora';
            font-style: normal;
            font-weight: 700;
            src: url('file://{{ public_path("fonts/Lora-Bold.ttf") }}') format('truetype');
        }

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

        .hoja {
            padding-left: 5px;
            width: 18.5cm;
            height: 29cm;
        }

        @page {
            margin-left: 1.0cm;
            margin-right: 1.5cm;
            margin-top: 0.4cm;
            margin-bottom: 0.2cm;
        }

        body {
            margin: 0;
            padding: 0;
        }

        .etiquetas-maxi-container {
            width: 100%;
            border-spacing: 0;
            margin: 0;
            padding: 0;
        }

        .etiqueta-maxi {
            width: 5.2cm;
            height: 1.9cm;
            display: inline-block;
            vertical-align: top;
            margin: 10px 10px !important;
            padding: 0;
        }

        .etiqueta-maxi table {
            width: 100%;
            height: 100%;
            border-spacing: 0;
            margin: 0;
            padding: 0;
        }

        .etiqueta-maxi img {
            max-height: 100%;
            width: auto;
            display: block;
            margin: 0 auto;
        }

        .etiqueta-maxi-text {
            font-family: 'Oswald';
            font-size: 12pt;
            text-align: center;
            line-height: 0.75;
            color: #fff;
            margin: 0;
            padding: 0;
        }

        .etiqueta-maxi-text p.normal-text-size {
            font-size: 12pt !important;
        }

        .etiqueta-maxi-text p.small-text-size {
            font-size: 11pt !important;
            line-height: 0.7 !important;
        }

        /* NUMERO DE PEDIDO VERTICAL ABAJO A LA IZQUIERDA DE LA PANTALLA */
        .numeroOrder {
            position: absolute;
            bottom: 0%;
            left: 0%;
            transform: translate(-70%, -30%)
        }

        .numeroOrder p {
            transform: rotate(270deg);
            font-family: 'Oswald';
            font-size: large;
        }

        /* para que todas las celdas internas llenen su espacio */
        .etiqueta-maxi td {
            padding: 0;
            margin: 0;
        }

        /* FILA SPOT */
        .circulo-principal {
            width: 100%;
            height: auto;
            margin-left: 8px;
        }

        .circulo-personaje {
            width: 3cm;
            height: 3cm;
            margin-right: 4.85mm;
            margin-bottom: 4.63mm;
            vertical-align: top;
            display: inline-block;
            position: relative;
            border-radius: 50%;
        }

        .circulo-personaje p.normal-text-size {
            font-size: 12pt !important;
        }

        .circulo-personaje p.small-text-size {
            font-size: 10pt !important;
        }

        .circuloCuadro {
            width: 100%;
            height: 50%;
            position: relative;
        }

        .personajeCirculo {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            height: 70%;
        }

        .circulo-texto {
            width: 80%;
            text-align: center;
            margin: 10px auto 0;
            line-height: 0.8;
        }
    </style>
</head>

<body>
    <div class="hoja">

        <!-- FILA SPOT AGREGADA -->
        <div class="circulo-principal">
            @for ($i = 0; $i < 20; $i++)
                <div class="circulo-personaje">
                    <div class="circuloCuadro">
                        <img class="personajeCirculo" src="{{ $plantilla['imagen'] }}" alt="">
                    </div>
                    <p class="circulo-texto {{ $plantilla['fontClass'] }}"
                        style="font-family: 'Oswald';color:{{$plantilla['colores']}}">
                        {!! formatNameExactLines($product_order->name, 2) !!}
                    </p>
                </div>
            @endfor
        </div>

        <!-- TABLA DE ETIQUETAS -->
        <table class="etiquetas-maxi-container">
            @for ($row = 0; $row < 6; $row++)
                <tr>
                    @for ($col = 0; $col < 3; $col++)
                        <td class="etiqueta-maxi">
                            <table>
                                <tr>
                                    {{-- Mitad izquierda: imagen con fondo blanco --}}
                                    <td
                                        style="width:38%; height:100%; background:white; text-align:center; vertical-align:middle;">
                                        <img src="{{ $plantilla['imagen'] }}" alt="">
                                    </td>

                                    {{-- Mitad derecha: texto con fondo del color que llega --}}
                                    <td class="etiqueta-maxi-text"
                                        style="width:62%; height:100%; background: {{ $plantilla['colores'] }}; text-align:center; vertical-align:middle;">
                                        <p class="{{ $plantilla['fontClass'] }}" style="margin:0; line-height:1; color:#FFF;">
                                            {!! formatName($product_order->name) !!}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    @endfor
                </tr>
            @endfor
        </table>

        <!-- NUMERO DE PEDIDO -->
        <div class="numeroOrder">
            <p>PEDIDOs # {{$product_order->order->id_external}}</p>
        </div>
    </div>
</body>

</html>