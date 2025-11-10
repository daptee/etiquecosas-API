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
        {!! file_get_contents(public_path('css/etiquetas.css')) !!}
    </style>
</head>

<body>
    <div class="hoja">
        {{-- MAXI --}}
        <div>
            @for ($i = 0; $i < 2; $i++)
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
                <div class="cuadro">
                    <p class="texto2 texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
                </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto2 texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto2 texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto2 texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto2 texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto2 texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto2 texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto2 texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        @endfor
    </div>

    <div style="height: 28px; width: 100%;"></div>

    {{-- SEGUNDO DISEÑO --}}
    <div class="diseñoD">
        @for ($i = 0; $i < 2; $i++)
            <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <p class="textoCentradoVertical texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="textoCentradoVertical texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="textoCentradoVertical texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="textoCentradoVertical texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="textoCentradoVertical texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="textoCentradoVertical texto-negro" style="font-family: 'Oswald';font-size: 1.05em;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    @endfor
    </div>

    {{-- TERCER DISEÑO --}}
    <div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto4 texto-negro" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto4 texto-negro" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto4 texto-negro" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto4 texto-negro" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto4 texto-negro" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="cuadro">
                <p class="texto4 texto-negro" style="font-family: 'Oswald';font-size: 1.45em;">{{mb_strtoupper($product_order->name)}}</p>
            </div>
        </div>
    </div>

    {{-- SUPER MINI --}}
    <div class="supermini">
        @for ($i = 0; $i < 3; $i++)
            <div class="primer-color" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <p class="texto1 texto-negro" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundo-color" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="texto1 texto-negro" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="tercer-color" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="texto1 texto-negro" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="primer-color" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="texto1 texto-negro" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="segundo-color" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="texto1 texto-negro" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    <div class="tercer-color" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <p class="texto1 texto-negro" style="font-family: 'Oswald';font-size: x-small;">{{mb_strtoupper($product_order->name)}}</p>
    </div>
    @endfor
    </div>
    <div class="numeroOrder">
        <p class="texto3" style="font-family: 'Oswald';font-size: large;"> PEDIDO # {{$product_order->order->id_external}} </p>
    </div>
    </div>
</body>

</html>