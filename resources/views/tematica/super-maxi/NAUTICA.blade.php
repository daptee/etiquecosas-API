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

        {!! file_get_contents(public_path('css/etiquetas.css')) !!}
    </style>
</head>

<body>
    <div class="hoja">

        {{-- SUPER MAXI --}}
        <table class="etiquetas-super-maxi-container">
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][10] }}" alt=""></td>
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
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][4] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="height: 8px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][5] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][8] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="height: 8px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][10] }}" alt=""></td>
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
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][4] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="height: 8px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][5] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][8] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="height: 8px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][10] }}" alt=""></td>
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
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][4] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="height: 8px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][5] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][8] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="height: 8px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][5] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][10] }}" alt=""></td>
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
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][4] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{!! formatName($product_order->name) !!}</p>
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