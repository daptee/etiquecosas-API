<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Lora:400,700,400italic|Oswald:400,700" media="screen">
    <style type="text/css">
        @font-face {
            font-family: 'Lora';
            font-style: normal;
            font-weight: 400;
            src: url('file://{{ public_path("fonts/Lora-Regular.ttf") }}') format('truetype');
        }

        @font-face {
            font-family: 'Oswald';
            font-style: normal;
            font-weight: 400;
            src: url('file://{{ public_path("fonts/Oswald-Regular.ttf") }}') format('truetype');
        }

        .primer-color {
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

        .segundo-color {
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

        .tercer-color {
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
            line-height: 13px;
            color: white;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            margin-right: -50%;
            transform: translate(-50%, -55%)
        }

        .icon-cuadroGrande {
            width: 5.5cm;
            height: 3cm;
            margin-top: 10px;
            margin-left: 10px;
            margin-right: 5px;
            margin-bottom: 5px;
            vertical-align: top;
            display: inline-block;
            position: relative;
            bottom: 2;
            background: {{ $plantilla['colores'] }};
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .texto2 {
            text-align: center;
            line-height: 12px;
            color: white;
            margin: 0;
            font-family: 'Oswald';
            font-size: 1.15em;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .texto3 {
            transform: rotate(270deg);
        }

        .numeroOrder {
            position: absolute;
            bottom: 0%;
            left: 0%;
            transform: translate(-65%, -70%)
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
    </style>

<body>
    <div class="hoja">
        @for ($i = 0; $i < 2; $i++)
            <div class="icon-cuadroGrande">
                <p class="texto2">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="icon-cuadroGrande">
                <p class="texto2">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="icon-cuadroGrande">
                <p class="texto2">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        @endfor

        @for ($i = 0; $i < 3; $i++)
            <div class="icon-cuadro">
                <p class="texto2" style="font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="icon-cuadro">
                <p class="texto2" style="font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="icon-cuadro">
                <p class="texto2" style="font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        @endfor

        <div style="height: 12px;"></div>

        @for ($i = 0; $i < 10; $i++)
            <div class="primer-color">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="segundo-color">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="tercer-color">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="primer-color">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="segundo-color">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="tercer-color">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        @endfor

        <div class="numeroOrder">
            <p class="texto3" style="font-family: 'Oswald';font-size: large;"> PEDIDO # {{$product_order->order->id_external}} </p>
        </div>
    </div>
</body>

</html>
