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

        .cuadroRenglon {
            border-bottom-width: 1px;
            border-bottom-style: solid;
            border-bottom-color: #000;
            position: absolute;
            display: inline-block;
            position: relative;
            position: absolute;
            bottom: 10;
            align-self: justify;
            left: 0.5cm;
            width: 4.5cm
        }
        

        .etiquetas-maxi-container {
            width: 100%;
            border-spacing: 0;
            margin: 0 0 12px 0;
            padding: 0;
        }

        .icon-cuadro {
            width: 5.2cm;
            height: 1.9cm;
            margin-top: 10px;
            margin-left: 10px;
            margin-right: 5px;
            margin-bottom: 5px;
            vertical-align: top;
            display: inline-block;
            position: relative;
            background: {{ $plantilla['colores'] }};
        }

        .icon-cuadro .texto2 {
            text-align: center;
            line-height: 0.8;
            color: white;
            margin: 0;
            font-family: 'Oswald';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
        }

        .texto3 {
            transform: rotate(270deg);
        }

        .texto1 {
            line-height: 0.8;
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
        }

        .texto1 {
            text-align: center;
            line-height: 1;
            color: white;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            margin-right: -50%;
            transform: translate(-50%, -50%);
            white-space: nowrap;
        }
        
    </style>
</head>

<body>
    <div class="hoja">        
        <!-- TABLA DE ETIQUETAS -->
         <div class="etiquetas-maxi-container">
            @for ($i = 0; $i < 6; $i++)
                <div class="icon-cuadro">
                    <p class="texto2" style="font-size: 1.05em;">{!! formatName($product_order->name, 2) !!}</p>
                </div>
                <div class="icon-cuadro">
                    <p class="texto2" style="font-size: 1.05em;">{!! formatName($product_order->name, 2) !!}</p>
                </div>
                <div class="icon-cuadro">
                    <p class="texto2" style="font-size: 1.05em;">{!! formatName($product_order->name, 2) !!}</p>
                </div>
            @endfor
         </div>

        @for ($i = 0; $i < 60; $i++)
            <div class="super-mini" style="background: {{$plantilla['colores']}})">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">
                    {{mb_strtoupper($product_order->name)}}</p>
            </div>
        @endfor

        <!-- NUMERO DE PEDIDO -->
        <div class="numeroOrder">
            <p>PEDIDOs # {{$product_order->order->id_external}}</p>
        </div>
    </div>
</body>

</html>
