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
        .primer-color {
            width: 2.9cm;
            height: 1.15cm;
            margin-top: 2px;
            margin-left: 1.5px;
            margin-right: 1px;
            margin-bottom: 2px;
            display: inline-block;
            color: white;
            position: relative;
        }

        .segundo-color {
            width: 2.9cm;
            height: 1.15cm;
            margin-top: 2px;
            margin-left: 1.5px;
            margin-right: 1px;
            margin-bottom: 2px;
            display: inline-block;
            color: white;
            position: relative;
        }

        .tercer-color {
            width: 2.9cm;
            height: 1.15cm;
            margin-top: 2px;
            margin-left: 1.5px;
            margin-right: 1px;
            margin-bottom: 2px;
            display: inline-block;
            color: white;
            position: relative;
        }

        .icon-cuadro {
            width: 4.4cm;
            height: 2.4cm;
            margin-top: 2px;
            margin-left: 2px;
            margin-right: 2px;
            margin-bottom: 3px;
            vertical-align: top;
            display: inline-block;
            position: relative;
        }

        .cuadro {
            width: 2.7cm;
            height: 100%;
            position: relative;
            position: absolute;
            top: 0;
            left: 2cm;
        }

        .cuadroD {
            width: 3cm;
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

        .imagenT {
            width: 2.5cm;
            height: 100%;
            position: relative;
            position: absolute;
            top: 0;
            left: 0;
        }

        .personaje {
            width: 65%;
            height: auto;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .supermini {
            margin-top: -4;
        }

        .segundoD {
            width: 2.7cm;
            height: 3.2cm;
            margin-top: 5px;
            margin-left: 2px;
            margin-right: 9.1px;
            margin-bottom: 3px;
            color: white;
            display: inline-block;
            position: relative;
        }

        .imagenAbajo {
            width: 100%;
            height: 1.7cm;
            position: relative;
            position: absolute;
            bottom: 0;
            left: 0;
        }

        .imagenArriba {
            width: 100%;
            height: 1.7cm;
            position: relative;
            position: absolute;
            top: 0;
            left: 0;
        }

        .personaje2 {
            width: auto;
            height: 70%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .imagenAbajo .personaje2 {
            top: 45%;
        }

        .imagenArriba .personaje2 {
            top: 55%;
        }

        .cuadroS {
            width: 2.7cm;
            height: 100%;
            position: relative;
            position: absolute;
            top: 0;
            left: 2cm;
        }

        .tercerD {
            width: 6cm;
            height: 3.6cm;
            margin-top: 6px;
            margin-left: 1px;
            margin-right: 1px;
            margin-bottom: 2.5px;
            color: white;
            display: inline-block;
            position: relative;
        }

        .personaje3 {
            width: 80%;
            height: auto;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-35%, -50%);
        }

        .cuadroT {
            width: 2.7cm;
            height: 100%;
            position: relative;
            position: absolute;
            top: 0;
            left: 2cm;
        }

        .texto1 {
            text-align: center;
            line-height: 10px;
            color: white;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            margin-right: -50%;
            transform: translate(-50%, -50%)
        }

        .texto2 {
            text-align: center;
            line-height: 12px;
            color: white;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-75%, -50%)
        }

        .textoArriba {
            text-align: center;
            line-height: 12px;
            color: white;
            margin: 0;
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -98%)
        }

        .textoAbajo {
            text-align: center;
            line-height: 12px;
            color: white;
            margin: 0;
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -1%)
        }

        .texto4 {
            text-align: center;
            line-height: 17px;
            color: white;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-32%, -50%)
        }

        .texto3 {
            transform: rotate(270deg);
        }

        .numeroOrder {
            position: absolute;
            bottom: 0%;
            left: 0%;
            transform: translate(-70%, -30%)
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
</head>

<body>
    <div class="hoja">
        {{-- MAXI --}}
        <div>
            @for ($i = 0; $i < 2; $i++)
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
                <div class="imagen">
                    <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
                </div>
                <div class="cuadro">
                    <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
                </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][1] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][2] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][3] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][0] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][4] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][5] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][3] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        @endfor
    </div>

    <div style="height: 25px; width: 100%;"></div>

    {{-- SEGUNDO DISEÑO --}}
    <div class="diseñoD">
        @for ($i = 0; $i < 2; $i++)
            <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][1] }})">
            <div class="imagenAbajo">
                <img class="personaje2" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <p class="textoArriba" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][4] }})">
        <div class="imagenAbajo">
            <img class="personaje2" src="{{ $plantilla['imagen'][1] }}" alt="">
        </div>
        <p class="textoArriba" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <div class="imagenArriba">
            <img class="personaje2" src="{{ $plantilla['imagen'][1] }}" alt="">
        </div>
        <p class="textoAbajo" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][3] }})">
        <div class="imagenArriba">
            <img class="personaje2" src="{{ $plantilla['imagen'][0] }}" alt="">
        </div>
        <p class="textoAbajo" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][5] }})">
        <div class="imagenAbajo">
            <img class="personaje2" src="{{ $plantilla['imagen'][1] }}" alt="">
        </div>
        <p class="textoArriba" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][2] }})">
        <div class="imagenAbajo">
            <img class="personaje2" src="{{ $plantilla['imagen'][1] }}" alt="">
        </div>
        <p class="textoArriba" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    @endfor
    </div>

    {{-- TERCER DISEÑO --}}
    <div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][1] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][5] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][4] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][4] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][2] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
    </div>

    {{-- SUPER MINI --}}
    <div class="supermini">
        @for ($i = 0; $i < 3; $i++)
            <div class="primer-color" style="background: cmyk({{ $plantilla['colores'][1] }})">
            <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundo-color" style="background: cmyk({{ $plantilla['colores'][5] }})">
        <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="tercer-color" style="background: cmyk({{ $plantilla['colores'][2] }})">
        <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="primer-color" style="background: cmyk({{ $plantilla['colores'][3] }})">
        <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundo-color" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="tercer-color" style="background: cmyk({{ $plantilla['colores'][4] }})">
        <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    @endfor
    </div>
    <div class="numeroOrder">
        <p class="texto3" style="font-family: 'Oswald';font-size: large;"> PEDIDO # {{$product_order->order->id_external}} </p>
    </div>
    </div>
</body>

</html>