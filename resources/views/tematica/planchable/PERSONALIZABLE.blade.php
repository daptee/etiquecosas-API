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
            white-space: nowrap;
            line-height: 1cm;
            text-align: center;
        }

        .cuadro {
            display: inline-block;
            vertical-align: middle;
            line-height: 1cm;
            /*border: 1px solid blue;*/
        }
        .imagen {
            display: inline-block;
            vertical-align: middle;
            line-height: 1cm;
            text-align: center;
            /*border: 1px solid green;*/
        }
        .personaje {
            max-height: 0.8cm;
            margin-top: 7px;
            margin-right: 7px;
            vertical-align: middle;
            /*left: 63%;*/
            /*transform: translate(-440%, -50%);*/
            /*border: 1px solid red;*/
        }

        .texto2 {
            text-align: center;
            line-height: 12px;
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
            $nameLength = Str::length($product_order->name);
            if ($nameLength <= 16) {
                $fontsize = '1.1em';
            } elseif ($nameLength <= 25) {
                $fontsize = '0.9em';
            } else {
                $fontsize = '0.7em';
            }
        @endphp
        @for ($i = 0; $i < $plantilla['label']; $i++)
            <div class="icon-cuadro">
                <div class="imagen">
                    <img class="personaje" src="{{ $plantilla['imagen'] }}">
                </div>
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
