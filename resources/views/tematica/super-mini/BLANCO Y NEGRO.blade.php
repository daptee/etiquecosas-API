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

        <div style="height: 8px; width: 100%;"></div>

        {{-- SUPER MINI --}}
        <table class="etiquetas-super-mini-container">
            
            @for ($i = 0; $i < $plantilla['filas']; $i++)
                <tr>
                <td class="etiqueta-super-mini" style="background: #fff; color: #000000;">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }} texto-negro">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: #fff; color: #000000;">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }} texto-negro">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: #fff; color: #000000;">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }} texto-negro">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: #fff; color: #000000;">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }} texto-negro">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: #fff; color: #000000;">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }} texto-negro">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: #fff; color: #000000;">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }} texto-negro">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                </tr>
                <tr>
                    <td colspan="11" style="height: 12px;"></td>
                </tr>
            @endfor
        </table>

        <div class="numeroOrder">
            <p> PEDIDO # {{$product_order->order->id_external}} </p>
        </div>
    </div>
</body>

</html>
