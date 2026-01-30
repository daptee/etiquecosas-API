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
        {!! file_get_contents(public_path('css/vinilo.css')) !!}
    </style>
</head>

<body>
    <div class="hoja">
        <div style="margin-left: 20px;">
            @for ($i = 0; $i < $plantilla['columna']; $i++)
                <div class="columna">
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <div class="imagen">
                        <img class="personajeChico" src="{{ $plantilla['imagen'][9] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <div class="imagen">
                        <img class="personajeAlto" src="{{ $plantilla['imagen'][7] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2 texto-azul" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <div class="imagen">
                        <img class="personajeChico" src="{{ $plantilla['imagen'][1] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <div class="imagen">
                        <img class="personajeChico" src="{{ $plantilla['imagen'][0] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <div class="imagen">
                        <img class="personajeChico" src="{{ $plantilla['imagen'][8] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <div class="imagen">
                        <img class="personajeAlto" src="{{ $plantilla['imagen'][4] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2 texto-azul" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <div class="imagen">
                        <img class="personajeChico" src="{{ $plantilla['imagen'][6] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <div class="imagen">
                        <img class="personajeChico" src="{{ $plantilla['imagen'][2] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <div class="imagen">
                        <img class="personajeChico" src="{{ $plantilla['imagen'][8] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <div class="imagen">
                        <img class="personajeChico" src="{{ $plantilla['imagen'][5] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado texto-azul" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2 texto-azul" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <div class="imagen">
                        <img class="personajeChico" src="{{ $plantilla['imagen'][0] }}" alt="">
                    </div>
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2]) }}
                            </p>
                        </div>
                        @break
                        @case(4)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0] . ' ' . $name[1]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[2] . ' ' . $name[3]) }}
                            </p>
                        </div>
                        @break

                        @default
                        <p class="texto2" style="font-family: 'Oswald';font-size: 1.05em;">
                            {{ mb_strtoupper($product_order->name) }}
                        </p>
                        @endswitch
                    </div>
                </div>
        </div>
        @endfor
    </div>
    </div>
    <div class="numeroOrder">
        <p class="texto3" style="font-family: 'Oswald';font-size: large;"> PEDIDO #
            {{ $product_order->order->id_external }}
        </p>
    </div>
</body>

</html>