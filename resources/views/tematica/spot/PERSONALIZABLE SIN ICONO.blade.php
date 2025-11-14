<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Lora:400,700,400italic|Oswald:400,700" media="screen">
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

        @page {
            margin-left: 1.0cm;
            margin-right: 1.5cm;
            margin-top: 0.4cm;
            margin-bottom: 0.2cm;
        }

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
            width: 3cm;
            height: 3cm;
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

<body>
    <div class="hoja">
        @for ($i = 0; $i < 35; $i++)
            <div class="circulo-personaje">
                <p class="circulo-texto">
                    {{ formatNameExactLines($product_order->name, 2) }}
                </p>
            </div>
        @endfor
        <div class="numeroOrder">
            <p class="texto3" style="font-family: 'Oswald';font-size: large;"> PEDIDO # {{$product_order->order->id_external}} </p>
        </div>
    </div>
</body>

</html>
