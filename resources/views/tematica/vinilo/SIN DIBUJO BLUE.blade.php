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
        {!! file_get_contents(public_path('css/viniloSinDibujo.css')) !!}
    </style>
</head>

<body>
    <div class="hoja">
        <div style="margin-left: 20px;">
            @for ($i = 0; $i < $plantilla['columna']; $i++)
                <div class="columna">
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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
                <div class="icon-cuadro" style="background: cmyk({{ $plantilla['colores'][4] }})">
                    <div class="cuadro">
                        @php
                        $name = explode(' ', $product_order->name);
                        $cant = count($name);
                        @endphp
                        @switch($cant)
                        @case(3)
                        <div style="transform: translate(0%, -25%);">
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[0]) }}
                            </p>
                            <p class="textoAplanado" style="font-family: 'Oswald';font-size: 0.9em;">
                                {{ mb_strtoupper($name[1] . ' ' . $name[2]) }}
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