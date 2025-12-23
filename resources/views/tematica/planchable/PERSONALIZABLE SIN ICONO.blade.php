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
            display: inline-table;
            background: #FFFFFF;
        }

        .texto2 {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            color: {{ $plantilla['colores'] }};
            font-family: 'Oswald';
            padding: 0 5px;
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
        @for ($i = 0; $i < $plantilla['label']; $i++)
            <div class="icon-cuadro">
                <div class="texto2" style="font-size: {{ $fontsize }};">
                    {!! formatName($product_order->name, 1) !!}</div>
            </div>
        @endfor
        <div class="numeroOrder">
            <p class="texto3"> PEDIDOs #
                {{ $product_order->order->id_external }} </p>
        </div>
    </div>
</body>

</html>
