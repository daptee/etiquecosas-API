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
/* El editor centra el texto EN VIVO en su propio lienzo (flexbox, con el
   contenido real ya resuelto), no con una estimación. vertical_offset_cm
   es una estimación del editor (cuántos renglones va a ocupar el texto) y
   puede quedar levemente corrida respecto al centrado real — así que acá
   centramos de verdad con el texto ya armado (resolved_text_html), en vez
   de confiar en esa estimación. Crece simétrico hacia los dos lados si el
   contenido termina siendo más alto que la caja declarada. */
.editor-text-wrap {
    position: absolute;
    left: 0;
    width: 100%;
}
.editor-element p {
    margin: 0;
    line-height: 1.15;
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
                            // Prioridad de esquinas: si el elemento manda su propio
                            // radius_mode/radius_pct, gana eso; si no, se usa lo que
                            // haya resuelto la forma del catálogo (label_shape_id).
                            if (($el['radius_mode'] ?? null) === 'straight') {
                                $borderRadius = '0';
                            } elseif (isset($el['radius_pct'])) {
                                $borderRadius = ((float) $el['radius_pct']) . '%';
                            } else {
                                $shapeType = $el['resolved_shape_type'] ?? null;
                                $borderRadius = match ($shapeType) {
                                    'circle' => '50%',
                                    'rect' => ($el['resolved_shape_corner_radius_cm'] ?? 0) . 'cm',
                                    default => '0',
                                };
                            }

                            // padding_cm NO achica el color: es una zona segura para
                            // dónde el editor deja ubicar íconos/texto dentro de la
                            // etiqueta, no algo que afecte el relleno de color, que
                            // siempre llena el width_cm/height_cm completo.
                            $borderWidthCm = (float) ($el['border']['width_cm'] ?? 0);
                            $borderColor = $el['border']['color'] ?? null;
                            $borderCss = $borderWidthCm > 0
                                ? $borderWidthCm . 'cm solid ' . (($borderColor['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . $borderColor['value'] . ')' : ($borderColor['value'] ?? '#000000'))
                                : 'none';
                        @endphp
                        <div style="
                            box-sizing: border-box;
                            width: 100%;
                            height: 100%;
                            overflow: hidden;
                            border-radius: {{ $borderRadius }};
                            border: {{ $borderCss }};
                            background: {{ ($el['color']['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . $el['color']['value'] . ')' : ($el['color']['value'] ?? '#FFFFFF') }};
                        "></div>
                        @break

                    @case('icon')
                        @if (!empty($el['resolved_icon_path']))
                            <img src="file://{{ str_replace('\\', '/', $el['resolved_icon_path']) }}" style="width:100%; height:100%; object-fit: contain;">
                        @endif
                        @break

                    @case('text')
                        @php
                            // Centrado real (no una estimación): crece parejo hacia
                            // los dos lados desde el punto elegido si el texto termina
                            // siendo más alto que la caja declarada.
                            [$wrapTop, $wrapTransform] = match ($el['vertical_align'] ?? 'middle') {
                                'top' => ['0%', 'none'],
                                'bottom' => ['100%', 'translateY(-100%)'],
                                default => ['50%', 'translateY(-50%)'],
                            };

                            // Corrección óptica: el centrado geométrico (basado en la
                            // caja de línea completa) deja el texto visualmente "bajo"
                            // cuando no tiene descendentes (g,j,p,q,y) — el espacio de
                            // descendente de la fuente sigue contando en la caja aunque
                            // no se use. Se nudgea hacia arriba una fracción del tamaño
                            // de fuente para compensar.
                            $fontSizePxActual = $el['resolved_font_size_px'] ?? $el['font_size_px'] ?? 32;
                            $opticalNudgePx = $fontSizePxActual * 0.03;
                        @endphp
                        <div class="editor-text-wrap" style="
                            top: {{ $wrapTop }};
                            transform: {{ $wrapTransform }};
                            text-align: {{ $el['text_align'] ?? 'center' }};
                        ">
                            <p style="
                                margin-top: -{{ $opticalNudgePx }}px;
                                font-family: '{{ $el['resolved_font_family'] ?? 'sans-serif' }}';
                                font-size: {{ $fontSizePxActual }}px;
                                font-weight: {{ $el['font_weight'] ?? 400 }};
                                color: {{ ($el['color']['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . ($el['color']['value'] ?? '0,0,0,1') . ')' : ($el['color']['value'] ?? '#000000') }};
                            ">
                                {!! $el['resolved_text_html'] ?? ($el['resolved_text'] ?? '') !!}
                            </p>
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
