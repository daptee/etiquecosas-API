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
            line-height: 1;
            color: white;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            margin-right: -50%;
            transform: translate(-50%, -55%);
            white-space: nowrap;
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
            background: #FFFFFF;
        }

        .icon-cuadroGrande .texto2 {
            text-align: center;
            line-height: 0.8;
            color: {{ $plantilla['colores'] }};
            margin: 0;
            font-family: 'Oswald';
            font-size: 1.15em;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
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
            white-space: normal;
        }

        .texto3 {
            transform: rotate(270deg);
        }

        .texto1 {
            line-height: 0.8;
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
        {!! file_get_contents(public_path('css/etiquetas.css')) !!}
    </style>

<body>
    <div class="hoja">
        @for ($i = 0; $i < 2; $i++)
            <div class="icon-cuadroGrande">
                <p class="texto2">{!! formatName($product_order->name, 2, 15) !!}</p>
                <div class="cuadroRenglon"></div>
            </div>
            <div class="icon-cuadroGrande">
                <p class="texto2">{!! formatName($product_order->name, 2, 15) !!}</p>
                <div class="cuadroRenglon"></div>
            </div>
            <div class="icon-cuadroGrande">
                <p class="texto2">{!! formatName($product_order->name, 2, 15) !!}</p>
                <div class="cuadroRenglon"></div>
            </div>
        @endfor

        @for ($i = 0; $i < 3; $i++)
            <div class="icon-cuadro">
                <p class="texto2" style="font-size: 1.05em;">{!! formatName($product_order->name, 2, 15) !!}</p>
            </div>
            <div class="icon-cuadro">
                <p class="texto2" style="font-size: 1.05em;">{!! formatName($product_order->name, 2, 15) !!}</p>
            </div>
            <div class="icon-cuadro">
                <p class="texto2" style="font-size: 1.05em;">{!! formatName($product_order->name, 2, 15) !!}</p>
            </div>
        @endfor

        <div style="height: 12px;"></div>

        <table>
        @for ($i = 0; $i < 10; $i++)
            <tr>
                    <td class="etiqueta-super-mini" style="background: {{ $plantilla['colores'] }}">
                        <table class="etiqueta-super-mini-content-container">
                            <tr>
                                <td>
                                    <p class="{{ $plantilla['fontClass'] }}" >{!! formatName($product_order->name, 2, 15) !!}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 2px;"></td>
                    <td class="etiqueta-super-mini" style="background: {{ $plantilla['colores'] }}">
                        <table class="etiqueta-super-mini-content-container">
                            <tr>
                                <td>
                                    <p class="{{ $plantilla['fontClass'] }}" >{!! formatName($product_order->name, 2, 15) !!}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 2px;"></td>
                    <td class="etiqueta-super-mini" style="background: {{ $plantilla['colores'] }}">
                        <table class="etiqueta-super-mini-content-container">
                            <tr>
                                <td>
                                    <p class="{{ $plantilla['fontClass'] }}" >{!! formatName($product_order->name, 2, 15) !!}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 2px;"></td>
                    <td class="etiqueta-super-mini" style="background: {{ $plantilla['colores'] }}">
                        <table class="etiqueta-super-mini-content-container">
                            <tr>
                                <td>
                                    <p class="{{ $plantilla['fontClass'] }}" >{!! formatName($product_order->name, 2, 15) !!}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 2px;"></td>
                    <td class="etiqueta-super-mini" style="background: {{ $plantilla['colores'] }}">
                        <table class="etiqueta-super-mini-content-container">
                            <tr>
                                <td>
                                    <p class="{{ $plantilla['fontClass'] }}" >{!! formatName($product_order->name, 2, 15) !!}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 2px;"></td>
                    <td class="etiqueta-super-mini" style="background: {{ $plantilla['colores'] }}">
                        <table class="etiqueta-super-mini-content-container">
                            <tr>
                                <td>
                                    <p class="{{ $plantilla['fontClass'] }}" >{!! formatName($product_order->name, 2, 15) !!}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="11" style="height: 5px;"></td>
                </tr>
            @endfor
        </table>

        <div class="numeroOrder">
            <p class="texto3" style="font-family: 'Oswald';font-size: large;"> PEDIDO # {{$product_order->order->id_external}} </p>
        </div>
    </div>
</body>

</html>
