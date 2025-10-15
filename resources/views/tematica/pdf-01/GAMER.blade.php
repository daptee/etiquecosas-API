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

@php
    function formatName($name, $maxLines = 3, $maxCharsPerLine = 10)
    {
        $words = explode(' ', mb_strtoupper($name));
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            // Si agrego la palabra supera el límite de caracteres y aún no llegué a la penúltima línea
            if (strlen($currentLine . ' ' . $word) > $maxCharsPerLine && count($lines) < $maxLines - 1) {
                $lines[] = trim($currentLine);
                $currentLine = $word;
            } else {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            }
        }

        // Agrego la última línea
        $lines[] = trim($currentLine);

        // Si excede el número máximo de líneas, recorto a maxLines
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] .= '…'; // opcional: indica que se cortó
        }

        return implode('<br>', $lines);
    }
@endphp

<body>
    <div class="hoja">
        {{-- MAXI --}}
        <table class="etiquetas-maxi-container">
            <tr>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][6] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][14] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="texto-azul {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][3] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][8] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="7" style="height: 4px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][12] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img class="iconoExtraGrande" src="{{ $plantilla['imagen'][11] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                            <td><img src="{{ $plantilla['imagen'][6] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][14] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="texto-azul {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][3] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][8] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="7" style="height: 4px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][12] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-maxi-content-container">
                        <tr>
                            <td><img class="iconoExtraGrande" src="{{ $plantilla['imagen'][11] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][8] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="texto-azul {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][13] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][8] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][1] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="texto-azul {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 10px;"></td>
                <td class="etiqueta-vertical" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-vertical-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][13] }}" alt=""></td>
                        </tr>
                        <tr>
                            <td style="height: 0.2cm;"></td>
                        </tr>
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                            <td><img src="{{ $plantilla['imagen'][10] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][0] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="texto-azul {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][7] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="height: 4px;"></td>
            </tr>
            <tr>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][2] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][6] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 4px;"></td>
                <td class="etiqueta-super-maxi" style="background: cmyk({{ $plantilla['colores'][3] }})">
                    <table class="etiqueta-super-maxi-content-container">
                        <tr>
                            <td><img src="{{ $plantilla['imagen'][4] }}" alt=""></td>
                            <td style="width: 0.3cm;"></td>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="texto-azul {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][2] }})">
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
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="texto-azul {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][2] }})">
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
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][2] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][1] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="texto-azul {{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
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
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][0] }})">
                    <table class="etiqueta-super-mini-content-container">
                        <tr>
                            <td>
                                <p class="{{ $plantilla['fontClass'] }}">{{mb_strtoupper($product_order->name)}}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 6px;"></td>
                <td class="etiqueta-super-mini" style="background: cmyk({{ $plantilla['colores'][2] }})">
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