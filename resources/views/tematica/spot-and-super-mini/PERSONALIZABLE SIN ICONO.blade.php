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

        .super-mini {
            width: 2.9cm;
            height: 1.15cm;
            margin-top: 7px;
            margin-left: 1.5px;
            margin-right: 1px;
            margin-bottom: 5px;
            display: inline-block;
            color: white;
            position: relative;
            background: {{ $plantilla['colores'] }};
        }

        .texto1 {
            text-align: center;
            line-height: 0.8;
            color: white;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            margin-right: -50%;
            transform: translate(-50%, -50%)
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
        
        .circulo-principal {
            width: 100%;
            height: auto;
            margin-left: 8px;
        }

        /* FILA SPOT */
        .circulo-personaje {
            width: 3cm;
            height: 3cm;
            margin-right: 4.85mm;
            margin-bottom: 4.63mm;
            vertical-align: top;
            display: inline-block;
            position: relative;
            border-radius: 50%;
            background: #FFF;
        }

        .circulo-personaje p.normal-text-size {
            font-size: 12pt !important;
        }

        .circulo-personaje p.small-text-size {
            font-size: 10pt !important;
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
        <div class="circulo-principal">
            @for ($i = 0; $i < 20; $i++)
                <div class="circulo-personaje">
                    <p class="circulo-texto {{ $plantilla['fontClass'] }}" style="font-family: 'Oswald';">
                        {!! formatNameExactLines($product_order->name, 2) !!}
                    </p>
                </div>
            @endfor
        </div>

        <!-- SUPER MINI -->
        @for ($i = 0; $i < 60; $i++)
            <div class="super-mini">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">
                    {{mb_strtoupper($product_order->name)}}</p>
            </div>
        @endfor

        <!-- NUMERO DE PEDIDO -->
        <div class="numeroOrder">
            <p>PEDIDO # {{$product_order->order->id_external}}</p>
        </div>
    </div>
</body>

</html>
