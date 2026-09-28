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
}
.editor-text-wrap {
    display: table;
    width: 100%;
    height: 100%;
}
.editor-text-cell {
    display: table-cell;
}
.editor-element p {
    margin: 0;
    line-height: 1.1;
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
            @php
                $rotationDeg = (float) ($el['rotation_deg'] ?? 0);
            @endphp
            <div class="editor-element" style="
                left: {{ $el['x_cm'] ?? 0 }}cm;
                top: {{ $el['y_cm'] ?? 0 }}cm;
                width: {{ $el['width_cm'] ?? 1 }}cm;
                height: {{ $el['height_cm'] ?? 1 }}cm;
                z-index: {{ $el['z_index'] ?? 0 }};
                @if ($rotationDeg) transform: rotate({{ $rotationDeg }}deg); @endif
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
                            // padding_cm insetea el color hacia adentro de su propia caja
                            // (un div vacío no se achica con "padding" normal de CSS).
                            $paddingCm = (float) ($el['padding_cm'] ?? 0);
                            $innerWidthCm = max(0, ($el['width_cm'] ?? 1) - 2 * $paddingCm);
                            $innerHeightCm = max(0, ($el['height_cm'] ?? 1) - 2 * $paddingCm);
                        @endphp
                        <div style="
                            width: {{ $innerWidthCm }}cm;
                            height: {{ $innerHeightCm }}cm;
                            margin: {{ $paddingCm }}cm;
                            overflow: hidden;
                            border-radius: {{ $borderRadius }};
                            background: {{ ($el['color']['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . $el['color']['value'] . ')' : ($el['color']['value'] ?? '#FFFFFF') }};
                        "></div>
                        @break

                    @case('icon')
                        @if (!empty($el['resolved_icon_path']))
                            <img src="file://{{ str_replace('\\', '/', $el['resolved_icon_path']) }}" style="width:100%; height:100%;">
                        @endif
                        @break

                    @case('text')
                        <div class="editor-text-wrap">
                            <div class="editor-text-cell" style="
                                vertical-align: {{ $el['vertical_align'] ?? 'middle' }};
                                text-align: {{ $el['text_align'] ?? 'center' }};
                            ">
                                <p style="
                                    position: relative;
                                    top: {{ $el['vertical_offset_cm'] ?? 0 }}cm;
                                    font-family: '{{ $el['resolved_font_family'] ?? 'sans-serif' }}';
                                    font-size: {{ $el['resolved_font_size_px'] ?? $el['font_size_px'] ?? 32 }}px;
                                    font-weight: {{ $el['font_weight'] ?? 400 }};
                                    color: {{ ($el['color']['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . ($el['color']['value'] ?? '0,0,0,1') . ')' : ($el['color']['value'] ?? '#000000') }};
                                ">
                                    {!! $el['resolved_text_html'] ?? ($el['resolved_text'] ?? '') !!}
                                </p>
                            </div>
                        </div>
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
