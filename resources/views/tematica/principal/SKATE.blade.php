<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Lora:400,700,400italic|Oswald:400,700" media="screen">
    
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

        /* ETIQUETAS MAXI */
        .etiquetas-maxi-container {
            margin: 0 !important;
            padding: 0 !important;
            border-spacing: 0 !important;
        }

        .etiquetas-maxi-container tr td {
            margin: 0 !important;
            padding: 0 !important;
        }

        .etiqueta-maxi {
            width: 4.4cm;
            height: 2.4cm;
            vertical-align: top;
            display: inline-block;
            position: relative;
        }

        .etiqueta-maxi-content-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            height: 1.2cm;
            width: auto;
            margin: 0 !important;
            padding: 0 !important;
            border-spacing: 0 !important;
        }

        .etiqueta-maxi-content-container tr td {
            margin: 0 !important;
            padding: 0 !important;
            width: fit-content;
            vertical-align: middle;
        }

        .etiqueta-maxi-content-container img {
            height: 1.2cm;
            width: auto;
            margin: 0 2px 0 10px !important;
        }

        .etiqueta-maxi-content-container img.iconoGrande {
            height: 1.1cm;
            width: auto;
        }

        .etiqueta-maxi-content-container img.iconoMediano {
            height: 1.3cm;
            width: auto;
        }

        .etiqueta-maxi-content-container p {
            font-family: 'Oswald';
            font-size: 12pt;
            text-align: center;
            line-height: 0.75;
            color: #FFF;
            margin: 0 auto !important;
            padding: 2px 0 0px 0 !important;
        }



        /* ETIQUETAS VERTICALES */
        .etiquetas-verticales-container {
            margin: 0 !important;
            padding: 0 !important;
            border-spacing: 0 !important;
        }

        .etiqueta-vertical {
            width: 2.7cm;
            height: 3.2cm;
            display: inline-block;
            position: relative;
        }

        .etiqueta-vertical-content-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 2.05cm;
            height: auto;
            margin: 0 !important;
            padding: 0 !important;
            border-spacing: 0 !important;
            text-align: center;
        }

        .etiqueta-vertical-content-container tr td {
            margin: 0 !important;
            padding: 0 !important;
            width: fit-content;
            vertical-align: middle;
        }

        .etiqueta-vertical-content-container img {
            width: auto;
            height: 1.2cm;
            margin: 0 auto !important;
        }

        .etiqueta-vertical-content-container p {
            color: #FFF;
            font-family: 'Oswald';
            text-align: center;
            line-height: 0.8;
            margin: 0 !important;
            padding: 2px 0 0px 0 !important;
        }


        /* ETIQUETAS SUPER MAXI */
        .etiquetas-super-maxi-container {
            margin: 0 !important;
            padding: 0 !important;
            border-spacing: 0 !important;
        }

        .etiquetas-super-maxi-container tr td {
            margin: 0 !important;
            padding: 0 !important;
        }

        .etiqueta-super-maxi {
            width: 6cm;
            height: 3.6cm;
            display: inline-block;
            position: relative;
        }

        .etiqueta-super-maxi-content-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            height: 1.8cm;
            width: auto;
            margin: 0 !important;
            padding: 0 !important;
            border-spacing: 0 !important;
        }

        .etiqueta-super-maxi-content-container tr td {
            margin: 0 !important;
            padding: 0 !important;
            width: fit-content;
            vertical-align: middle;
        }

        .etiqueta-super-maxi-content-container img {
            height: 1.8cm;
            width: auto;
            margin: 0 auto !important;
        }

        .etiqueta-super-maxi-content-container img.iconoGrande {
            height: 1.6cm;
            width: auto;
        }

        .etiqueta-super-maxi-content-container img.iconoMediano {
            height: 1.7cm;
            width: auto;
        }

        .etiqueta-super-maxi-content-container img.iconoChico {
            height: 2cm;
            width: auto;
        }

        .etiqueta-super-maxi-content-container p {
            font-family: 'Oswald';
            font-size: 17.25pt;
            text-align: center;
            line-height: 0.70;
            /* line-height: 20pt; */
            color: #FFF;
            margin: 0 auto !important;
            padding: 2px 0 0px 0 !important;
        }

        /* ETIQUETAS SUPER MINI */
        .etiquetas-super-mini-container {
            margin: 0 !important;
            padding: 0 !important;
            border-spacing: 0 !important;
        }

        .etiquetas-super-mini-container tr td {
            margin: 0 !important;
            padding: 0 !important;
        }

        .etiqueta-super-mini {
            width: 2.9cm;
            height: 1.15cm;
            display: inline-block;
            position: relative;
        }

        .etiqueta-super-mini-content-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 2.4cm;
            height: auto;
            margin: 0 !important;
            padding: 0 !important;
            border-spacing: 0 !important;
            text-align: center;
            /* border: 1px solid red; */
        }

        .etiqueta-super-mini-content-container tr td {
            margin: 0 !important;
            padding: 0 !important;
            height: fit-content;
            vertical-align: middle;
            /* border: 1px solid red; */
        }

        .etiqueta-super-mini-content-container p {
            font-family: 'Oswald';
            font-size: 9pt;
            text-align: center;
            line-height: 0.8;
            color: #FFF;
            display: inline-block;
            margin: 0 !important;
            padding: 3px 0 0px 0 !important;
            /* border: 1px solid black; */
        }

        .texto-negro {
            color: #000 !important;
        }


        /* TAMAÑOS DE FUENTES SEGUN LA CLASE QUE LLEGA */
        .large-text-size {
            font-size: 13pt !important;
        }

        .normal-text-size {
            font-size: 12pt !important;
        }

        .etiqueta-super-maxi-content-container p.normal-text-size {
            font-size: 17.25pt !important;
        }

        .etiqueta-super-mini-content-container p.normal-text-size {
            font-size: 9pt !important;
        }

        .small-text-size {
            font-size: 11pt !important;
            line-height: 0.8 !important;
        }

        .etiqueta-maxi-content-container p.small-text-size {
            font-size: 12pt !important;
        }

        .etiqueta-super-maxi-content-container p.small-text-size {
            font-size: 15pt !important;
        }

        .etiqueta-super-mini-content-container p.small-text-size {
            font-size: 9pt !important;
        }

        .extra-small-text-size {
            font-size: 10pt !important;
            line-height: 0.7 !important;
        }

        .etiqueta-maxi-content-container p.extra-small-text-size {
            font-size: 11pt !important;
            line-height: 0.8 !important;
        }

        .etiqueta-super-maxi-content-container p.extra-small-text-size {
            font-size: 14pt !important;
        }

        .etiqueta-super-mini-content-container p.extra-small-text-size {
            font-size: 8pt !important;
            line-height: 0.8 !important;
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
        {{-- MAXI --}}
        <table class="etiquetas-maxi-container">
            <tr>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][9] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][3] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][6] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][11] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="7" style="height: 4px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][4] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][0] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][6] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][2] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="7" style="height: 4px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][9] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][3] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][6] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][11] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="7" style="height: 4px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][4] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][0] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][6] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][2] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div style="height: 10px; width: 100%;"></div>

        {{-- ETIQUETAS VERTICALES --}}
        <table class="etiquetas-verticales-container">
            <tr>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][3] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][10] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][9] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][5] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][6] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][2] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="11" style="height: 6px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][3] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][10] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][9] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][5] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][6] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][2] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div style="height: 10px; width: 100%;"></div>

        {{-- SUPER MAXI --}}
        <table class="etiquetas-super-maxi-container">
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img class="iconoChico" src="{{ $plantilla['imagen'][6] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img class="iconoChico" src="{{ $plantilla['imagen'][8] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][3] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="height: 4px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img class="iconoChico" src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img class="iconoChico" src="{{ $plantilla['imagen'][0] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div style="height: 10px; width: 100%;"></div>

        {{-- SUPER MINI --}}
        <table class="etiquetas-super-mini-container">
            <tr>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][6] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="11" style="height: 10px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][6] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="11" style="height: 10px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="texto-negro {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][6] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="numeroOrder">
            <p> PEDIDO # {{$product_order->order->id_external}} </p>
        </div>
    </div>
</body>

</html>