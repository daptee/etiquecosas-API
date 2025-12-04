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
            /* border: solid 1px; */
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
        }

        .cuadroGrande {
            width: 3.2cm;
            line-height: 0.8 !important;
            height: 40%;
            position: relative;
            position: absolute;
            top: 10;
            left: 2.1cm;
        }

        .imagenGrande {
            height: 1.2cm;
            position: relative;
            position: absolute;
            top: 10;
            left: 10;
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
        }

        .cuadro {
            width: 3.2cm;
            height: 100%;
            position: relative;
            position: absolute;
            top: 0;
            left: 2cm;
        }

        .imagen {
            width: 2cm;
            height: 100%;
            position: relative;
            position: absolute;
            top: 0;
            left: 0;
        }

        .personaje {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .imagenGrande .personaje {
            height: 100%;
        }

        .imagen .personaje {
            height: 70%;
        }

        .texto2 {
            text-align: center;
            line-height: 12px;
            color: white;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -45%)
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
            margin: 10px 10px !important;
            padding: 0;
        }

        .etiqueta-maxi table {
            width: 100%;
            height: 100%;
            border-spacing: 0;
            margin: 0;
            padding: 0;
        }

        .etiqueta-maxi img {
            height: 1.2cm;
            width: auto;
            display: block;
            margin: 0 auto;
        }

        .etiqueta-maxi-text {
            font-family: 'Oswald';
            font-size: 12pt;
            text-align: center;
            color: #fff;
            margin: 0;
            padding: 0;
        }

        .etiqueta-maxi-text p.normal-text-size {
            line-height: 0.8 !important;
            font-size: 12pt !important;
        }

        .etiqueta-maxi-text p.small-text-size {
            font-size: 11pt !important;
            line-height: 0.7 !important;
        }

        @page {
            margin-left: 1.0cm;
            margin-right: 1.5cm;
            margin-top: 0.4cm;
            margin-bottom: 0.2cm;
        }
    </style>

<body>
    <div class="hoja">@for ($i = 0; $i < 2; $i++)
            <div class="icon-cuadroGrande">
            <div>
                <div class="imagenGrande">
                    <img class="personaje" src="{{ $plantilla['imagen'] }}" alt="">
                </div>
                <div class="cuadroGrande">
                    <p class="texto2" style="font-family: 'Oswald'; color:{{$plantilla['colores']}};font-size: 1.15em;">{{mb_strtoupper($product_order->name)}}</p>
                </div>
            </div>
            <div class="cuadroRenglon">
            </div>
    </div>
    <div class="icon-cuadroGrande">
        <div>
            <div class="imagenGrande">
                <img class="personaje" src="{{ $plantilla['imagen'] }}" alt="">
            </div>
            <div class="cuadroGrande">
                <p class="texto2" style="font-family: 'Oswald'; color:{{$plantilla['colores']}};font-size: 1.15em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="cuadroRenglon">
            </div>
        </div>
    </div>
    <div class="icon-cuadroGrande">
        <div>
            <div class="imagenGrande">
                <img class="personaje" src="{{ $plantilla['imagen'] }}" alt="">
            </div>
            <div class="cuadroGrande">
                <p class="texto2" style="font-family: 'Oswald';color:{{$plantilla['colores']}};font-size: 1.15em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="cuadroRenglon">
            </div>
        </div>
    </div>
    @endfor
        <table class="etiquetas-maxi-container">
            @for ($row = 0; $row < 3; $row++)
                <tr>
                    @for ($col = 0; $col < 3; $col++)
                        <td class="etiqueta-maxi">
                            <table>
                                <tr>
                                    {{-- Mitad izquierda: imagen con fondo blanco --}}
                                    <td
                                        style="width:38%; height:100%; background:white; text-align:center; vertical-align:middle;">
                                        <img src="{{ $plantilla['imagen'] }}" alt="">
                                    </td>

                                    {{-- Mitad derecha: texto con fondo del color que llega --}}
                                    <td class="etiqueta-maxi-text"
                                        style="width:62%; height:100%; background: {{ $plantilla['colores'] }}; text-align:center; vertical-align:middle;">
                                        <p class="{{ $plantilla['fontClass'] }}" style="margin:0; line-height:1; color:#FFF;">
                                            {!! formatName($product_order->name, 2) !!}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    @endfor
                </tr>
            @endfor
        </table>

        <div style="height: 12px;"></div>

        @for ($i = 0; $i < 10; $i++)
            <div class="primer-color" style="background:{{$plantilla['colores']}};">
            <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="segundo-color" style="background:{{$plantilla['colores']}};">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="tercer-color" style="background:{{$plantilla['colores']}};">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="primer-color" style="background:{{$plantilla['colores']}};">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="segundo-color" style="background:{{$plantilla['colores']}};">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            <div class="tercer-color" style="background:{{$plantilla['colores']}};">
                <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
            @endfor
            <div class="numeroOrder">
                <p class="texto3" style="font-family: 'Oswald';font-size: large;"> PEDIDO # {{$product_order->order->id_external}} </p>
            </div>
            </div>
</body>

</html>