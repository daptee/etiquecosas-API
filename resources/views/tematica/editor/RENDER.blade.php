<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
{!! file_get_contents(public_path('css/etiquetas.css')) !!}

/* La hoja del editor debe coincidir 1:1 con la hoja física: sin los
   márgenes de @page heredados de etiquetas.css (legacy), o (0,0) en el
   editor queda desplazado respecto al borde real del PDF. */
@page {
    margin: 0;
}

@foreach ($plantilla['design']['pages'] as $page)
    @foreach ($page['elements'] as $el)
        @if (($el['type'] ?? null) === 'text' && !empty($el['resolved_font_family']) && !empty($el['resolved_font_files'][0]))
            @php
                $fontPath = str_replace('\\', '/', $el['resolved_font_files'][0]);
                $fontFormat = match (strtolower(pathinfo($fontPath, PATHINFO_EXTENSION))) {
                    'otf' => 'opentype',
                    'woff' => 'woff',
                    'woff2' => 'woff2',
                    default => 'truetype',
                };
            @endphp
            @font-face {
                font-family: '{{ $el['resolved_font_family'] }}';
                src: url('file://{{ $fontPath }}') format('{{ $fontFormat }}');
            }
        @endif
    @endforeach
@endforeach

.editor-sheet {
    position: relative;
}
.editor-element {
    position: absolute;
    overflow: hidden;
}
.editor-element p {
    margin: 0;
    width: 100%;
    height: 100%;
    text-align: center;
}
</style>
</head>
<body>
@foreach ($plantilla['design']['pages'] as $page)
    <div class="editor-sheet" style="
        width: {{ $page['sheet']['width_cm'] ?? 18.5 }}cm;
        height: {{ $page['sheet']['height_cm'] ?? 29 }}cm;
        @if (!$loop->last) page-break-after: always; @endif
    ">
        @foreach ($page['elements'] as $el)
            <div class="editor-element" style="
                left: {{ $el['x_cm'] ?? 0 }}cm;
                top: {{ $el['y_cm'] ?? 0 }}cm;
                width: {{ $el['width_cm'] ?? 1 }}cm;
                height: {{ $el['height_cm'] ?? 1 }}cm;
                z-index: {{ $el['z_index'] ?? 0 }};
            ">
                @switch($el['type'] ?? null)
                    @case('background')
                        @php
                            $shapeType = $el['resolved_shape_type'] ?? null;
                            $borderRadius = match ($shapeType) {
                                'circle' => '50%',
                                'rect' => ($el['resolved_shape_corner_radius_cm'] ?? 0) . 'cm',
                                default => '0',
                            };
                        @endphp
                        <div style="width:100%; height:100%; border-radius: {{ $borderRadius }}; background: {{ ($el['color']['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . $el['color']['value'] . ')' : ($el['color']['value'] ?? '#FFFFFF') }};"></div>
                        @break

                    @case('icon')
                        @if (!empty($el['resolved_icon_path']))
                            <img src="file://{{ str_replace('\\', '/', $el['resolved_icon_path']) }}" style="width:100%; height:100%;">
                        @endif
                        @break

                    @case('text')
                        <p style="
                            font-family: '{{ $el['resolved_font_family'] ?? 'sans-serif' }}';
                            font-size: {{ $el['font_size_px'] ?? 32 }}px;
                            color: {{ ($el['color']['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . ($el['color']['value'] ?? '0,0,0,1') . ')' : ($el['color']['value'] ?? '#000000') }};
                        ">
                            {!! formatName($el['resolved_text'] ?? '', 3, 10, $product_order->firstName ?? null) !!}
                        </p>
                        @break

                    @case('shape')
                        {{-- reservado: formas personalizadas desde label_shapes.data.outline_svg --}}
                        @break
                @endswitch
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
