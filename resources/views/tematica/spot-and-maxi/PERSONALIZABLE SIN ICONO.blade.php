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
            margin: 10px 10px !important;
            padding: 0;
            background: {{ $plantilla['colores'] }};
            text-align: center;
            position: relative;
        }

        .etiqueta-maxi-text {
            font-family: 'Oswald';
            font-size: 14pt;
            text-align: center;
            color: #fff;
            margin: 0;
            padding: 0;
            width: 100%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .etiqueta-maxi-text p {
            line-height: 0.8;
            margin: 0;
        }

        .etiqueta-maxi-text p.normal-text-size {
            font-size: 14pt !important;
        }

        .etiqueta-maxi-text p.small-text-size {
            font-size: 12pt !important;
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

        /* FILA SPOT */
        .circulo-personaje {
            width: 3.4cm;
            height: 3.4cm;
            margin-right: 5px;
            margin-bottom: 0.7cm;
            vertical-align: top;
            display: inline-block;
            position: relative;
            border-radius: 50%;
            background: #FFF;
        }

        .circulo-texto {
            width: 80%;
            text-align: center;
            margin: 10px auto 0;
            line-height: 0.8;
            color: {{ $plantilla['colores'] }};
            font-family: 'Oswald';
            font-size: small;
            padding-top: 0.8cm;
        }
    </style>
</head>

<body>
    <div class="hoja">

        <!-- FILA SPOT AGREGADA -->
        @for ($i = 0; $i < 10; $i++)
            <div class="circulo-personaje">
                <p class="circulo-texto">
                    {{ mb_strtoupper($product_order->name) }}
                </p>
            </div>
        @endfor

        <!-- TABLA DE ETIQUETAS -->
        <table class="etiquetas-maxi-container">
            @for ($row = 0; $row < 8; $row++)
                <tr>
                    @for ($col = 0; $col < 3; $col++)
                        <td class="etiqueta-maxi">
                            <div class="etiqueta-maxi-text">
                                <p class="{{ $plantilla['fontClass'] }}" style="margin:0; color:#FFF;">
                                    {!! formatName($product_order->name, 2) !!}
                                </p>
                            </div>
                        </td>
                    @endfor
                </tr>
            @endfor
        </table>

        <!-- NUMERO DE PEDIDO -->
        <div class="numeroOrder">
            <p>PEDIDO # {{$product_order->order->id_external}}</p>
        </div>
    </div>
</body>

</html>
