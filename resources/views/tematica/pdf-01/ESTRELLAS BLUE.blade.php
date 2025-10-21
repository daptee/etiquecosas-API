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
                <div class="imagen">
                    <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
                </div>
                <div class="cuadro">
                    <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
                </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][1] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][2] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][3] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][0] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][4] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][5] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][3] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="imagen">
                <img class="personaje" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadro">
                <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
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
            <p class="textoArriba" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][4] }})">
        <div class="imagenAbajo">
            <img class="personaje2" src="{{ $plantilla['imagen'][1] }}" alt="">
        </div>
        <p class="textoArriba" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][0] }})">
        <div class="imagenArriba">
            <img class="personaje2" src="{{ $plantilla['imagen'][1] }}" alt="">
        </div>
        <p class="textoAbajo" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][3] }})">
        <div class="imagenArriba">
            <img class="personaje2" src="{{ $plantilla['imagen'][0] }}" alt="">
        </div>
        <p class="textoAbajo" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][5] }})">
        <div class="imagenAbajo">
            <img class="personaje2" src="{{ $plantilla['imagen'][1] }}" alt="">
        </div>
        <p class="textoArriba" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
    </div>
    <div class="segundoD" style="background: cmyk({{ $plantilla['colores'][2] }})">
        <div class="imagenAbajo">
            <img class="personaje2" src="{{ $plantilla['imagen'][1] }}" alt="">
        </div>
        <p class="textoArriba" style="font-family: 'Oswald';font-size: 1.05em;">{!! formatName($product_order->name) !!}</p>
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
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][5] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][4] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][4] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][0] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{!! formatName($product_order->name) !!}</p>
            </div>
        </div>
        <div class="tercerD" style="background: cmyk({{ $plantilla['colores'][2] }})">
            <div class="imagenT">
                <img class="personaje3" src="{{ $plantilla['imagen'][1] }}" alt="">
            </div>
            <div class="cuadroD">
                <p class="texto4" style="font-family: 'Oswald';font-size: 1.45em;">{!! formatName($product_order->name) !!}</p>
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