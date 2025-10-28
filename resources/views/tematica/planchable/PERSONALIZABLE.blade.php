<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css"
        href="https://fonts.googleapis.com/css?family=Lora:400,700,400italic|Oswald:400,700" media="screen">
    <style type="text/css">
        .icon-cuadro {
            border: solid 1px;
            width: 6cm;
            height: 1cm;
            margin-top: 10px;
            margin-left: 1px;
            margin-right: 0;
            margin-bottom: 5px;
            vertical-align: top;
            display: inline-block;
            position: relative;
        }

        .cuadro {
            width: 4cm;
            height: 100%;
            position: relative;
            top: 0;
            left: 2cm;
            background: #FFFFFF;
            /*border: 1px solid blue;*/
        }
        .imagen {
            width: 2cm;
            height: 100%;
            /*border: 1px solid green;*/
            position: relative;
            position: absolute;
            top: 0;
            left: 0;
        }
        .personaje {
            max-height: 80%;
            max-width: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            /*left: 63%;*/
            /*transform: translate(-440%, -50%);*/
            /*border: 1px solid red;*/
        }

        .texto2 {
            width: 4cm;
            text-align: center;
            line-height: 12px;
            color: white;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-60%, -45%);
            font-family: 'Oswald';
        }

        .texto3 {
            transform: rotate(270deg);
            font-family: 'Oswald';
            font-size: large;
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
        @php
            $fontsize = '1.1em';
            if (Str::length($product_order->name) > 16) {
                $fontsize = '0.9em';
            }
        @endphp
        @for ($i = 0; $i < 8; $i++)
            <div class="icon-cuadro">
                <div class="imagen">
                    <img class="personaje" src="{{ $plantilla['imagen'] }}">
                </div>
                <div class="cuadro">
                    <p class="texto2" style="color:{{ $plantilla['colores'] }};font-size: {{ $fontsize }};">
                        {{ mb_strtoupper($product_order->name)  }}</p>
                </div>
            </div>
            <div class="icon-cuadro">
                <div class="imagen">
                    <img class="personaje" src="{{ $plantilla['imagen'] }}">
                </div>
{{--                <img class="personaje" src="{{ $plantilla['imagen'] }}">--}}
                <div class="cuadro">
                    <p class="texto2" style="color:{{ $plantilla['colores'] }};font-size: {{ $fontsize }};">
                        {{ mb_strtoupper($product_order->name)  }}</p>
                </div>
            </div>
            <div class="icon-cuadro">
                <div class="imagen">
                    <img class="personaje" src="{{ $plantilla['imagen'] }}">
                </div>
{{--                <img class="personaje" src="{{ $plantilla['imagen'] }}">--}}
                <div class="cuadro">
                    <p class="texto2" style="color:{{ $plantilla['colores'] }};font-size: {{ $fontsize }};">
                        {{ mb_strtoupper($product_order->name)  }}</p>
                </div>
            </div>
        @endfor
        <div class="numeroOrder">
            <p class="texto3"> PEDIDO #
                {{ $product_order->order->id_external }} </p>
        </div>
    </div>
</body>

</html>
