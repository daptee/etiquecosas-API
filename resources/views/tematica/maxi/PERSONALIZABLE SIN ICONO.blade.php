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
            margin: 14px 10px !important;
            padding: 0;
            background: {{ $plantilla['colores'] }};
            text-align: center;
        }

        .etiqueta-maxi-text {
            font-family: 'Oswald';
            font-size: 14pt;
            text-align: center;
            line-height: 1.9cm;
            color: #fff;
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
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
    </style>
</head>

<body>

    <div class="hoja">
        <table class="etiquetas-maxi-container">
            @for ($row = 0; $row < 11; $row++)
                <tr>
                    @for ($col = 0; $col < 3; $col++)
                        <td class="etiqueta-maxi">
                            <div class="etiqueta-maxi-text">
                                <p class="{{ $plantilla['fontClass'] }}" style="margin:0; color:#FFF;">
                                    {!! formatName($product_order->name) !!}
                                </p>
                            </div>
                        </td>
                    @endfor
                </tr>
            @endfor
        </table>

        <div class="numeroOrder">
            <p>PEDIDO # {{$product_order->order->id_external}}</p>
        </div>

    </div>
</body>

</html>
