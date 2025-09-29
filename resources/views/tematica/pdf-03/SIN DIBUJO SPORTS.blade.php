<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Lora:400,700,400italic|Oswald:400,700" media="screen">
    <style type="text/css">
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

        .texto3 {
            transform: rotate(270deg);
        }

        .numeroOrder {
            position: absolute;
            bottom: 0%;
            left: 0%;
            transform: translate(-70%, -40%)
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
        <div style="height: 12px; width: 100%;"></div>
        @for ($i = 0; $i < $plantilla['filas']; $i++)
            <div class="primer-color" style="background: cmyk({{ $plantilla['colores'][5] }})">
            <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundo-color" style="background: cmyk({{ $plantilla['colores'][3] }})">
        <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="tercer-color" style="background: cmyk({{ $plantilla['colores'][6] }})">
        <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="primer-color" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="texto1" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundo-color" style="background: cmyk({{ $plantilla['colores'][1] }})">
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
</body>

</html>