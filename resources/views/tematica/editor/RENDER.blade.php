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
            @endphp
            {{-- OJO: format() SIEMPRE tiene que decir 'truetype', sea cual sea
                 la extensión real (.ttf/.otf/.woff/.eot). El Stylesheet.php de
                 esta versión de dompdf descarta la @font-face entera si el
                 format declarado no es literalmente el string "truetype" (ver
                 vendor/dompdf/dompdf/src/Css/Stylesheet.php:1646) — así que
                 poner 'opentype' para un .otf hace que dompdf ni siquiera
                 intente cargar el archivo. php-font-lib, aparte, detecta el
                 tipo real del archivo por sus primeros bytes (no por este
                 string), así que decir "truetype" acá no rompe nada. --}}
            {{-- font-weight tiene que matchear el que usa el <p> de este elemento:
                 la tipografía subida no distingue variantes por peso (un solo
                 archivo), así que si se declara la @font-face como "normal" pero
                 el texto pide font-weight:700, dompdf no encuentra un "bold" de
                 esa familia y cae directo a Times-Bold en vez de usar el único
                 archivo que sí tiene registrado. --}}
            @font-face {
                font-family: '{{ $el['resolved_font_family'] }}';
                src: url('file://{{ $fontPath }}') format('truetype');
                font-weight: {{ $el['font_weight'] ?? 400 }};
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
                            $declaredWidthCm = (float) ($el['width_cm'] ?? 1);
                            $declaredHeightCm = (float) ($el['height_cm'] ?? 1);

                            // Radio de esquina en CM (no %): se calcula sobre el tamaño
                            // DECLARADO del elemento, igual que hace el editor — así el
                            // resultado visual da lo mismo tenga o no borde (ver nota de
                            // abajo sobre por qué el % de CSS no sirve acá).
                            if (($el['radius_mode'] ?? null) === 'straight') {
                                $borderRadius = '0';
                            } elseif (isset($el['radius_pct'])) {
                                $radiusCm = min($declaredWidthCm, $declaredHeightCm) * ((float) $el['radius_pct']) / 100;
                                $borderRadius = $radiusCm . 'cm';
                            } else {
                                $shapeType = $el['resolved_shape_type'] ?? null;
                                $borderRadius = match ($shapeType) {
                                    'circle' => '50%',
                                    'rect' => ($el['resolved_shape_corner_radius_cm'] ?? 0) . 'cm',
                                    default => '0',
                                };
                            }

                            // dompdf pinta el fondo (background) hasta el borde exterior
                            // del content-box (content + 2×borde), igual que el spec CSS
                            // de background-clip:border-box — así que para que el total
                            // (fondo+borde) termine midiendo justo el width_cm/height_cm
                            // declarado, el <div> tiene que declarar su tamaño ya restado
                            // el borde (content-box). NO hay que agregarle además un
                            // margin: dompdf no compensa ese margin corriendo el resto del
                            // contenido, así que sólo desplaza el dibujo entero hacia
                            // adentro sin encoger nada, y el conjunto termina invadiendo la
                            // etiqueta de al lado por el mismo ancho del borde.
                            $borderWidthCm = (float) ($el['border']['width_cm'] ?? 0);
                            $borderColor = $el['border']['color'] ?? null;
                            $borderCss = $borderWidthCm > 0
                                ? $borderWidthCm . 'cm solid ' . (($borderColor['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . $borderColor['value'] . ')' : ($borderColor['value'] ?? '#000000'))
                                : 'none';
                            $innerWidthCm = max(0, $declaredWidthCm - 2 * $borderWidthCm);
                            $innerHeightCm = max(0, $declaredHeightCm - 2 * $borderWidthCm);
                            $backgroundColorCss = ($el['color']['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . $el['color']['value'] . ')' : ($el['color']['value'] ?? '#FFFFFF');
                        @endphp
                        @if (($el['resolved_shape_type'] ?? null) === 'custom' && !empty($el['resolved_shape_outline_svg']))
                            @php
                                // El path viene dibujado en el sistema de coordenadas
                                // ORIGINAL del label_shape (su width_cm/height_cm de
                                // catálogo) — el viewBox tiene que ser ESE tamaño, no el
                                // del elemento ya reescalado en la página, y
                                // preserveAspectRatio="none" para que estire libremente
                                // hasta el width_cm/height_cm que el admin le puso acá.
                                // No soporta borde (border-box a mano no aplica a un path
                                // arbitrario): si el diseño le pone uno, se ignora.
                                $shapeViewBoxW = $el['resolved_shape_width_cm'] ?? $declaredWidthCm;
                                $shapeViewBoxH = $el['resolved_shape_height_cm'] ?? $declaredHeightCm;
                                $shapeSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $shapeViewBoxW . ' ' . $shapeViewBoxH . '" preserveAspectRatio="none">'
                                    . '<path d="' . $el['resolved_shape_outline_svg'] . '" fill="' . $backgroundColorCss . '" /></svg>';
                                $shapeSvgUri = 'data:image/svg+xml;base64,' . base64_encode($shapeSvg);
                            @endphp
                            <img src="{{ $shapeSvgUri }}" style="width: {{ $declaredWidthCm }}cm; height: {{ $declaredHeightCm }}cm;">
                        @else
                            <div style="
                                width: {{ $innerWidthCm }}cm;
                                height: {{ $innerHeightCm }}cm;
                                overflow: hidden;
                                border-radius: {{ $borderRadius }};
                                border: {{ $borderCss }};
                                background: {{ $backgroundColorCss }};
                            "></div>
                        @endif
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
                            // vertical_align "bottom": se le suma un empujón hacia abajo
                            // (fracción del tamaño de fuente) para compensar el espacio de
                            // interlineado invisible que queda por debajo de las letras —
                            // si no, el texto queda visualmente más arriba de lo esperado.
                            // "middle": empujón hacia arriba (fracción más chica), el
                            // centrado geométrico deja el texto un poco más abajo de lo
                            // esperado.
                            $fontSizePxActual = $el['resolved_font_size_px'] ?? $el['font_size_px'] ?? 32;
                            $bottomNudgePx = $fontSizePxActual * 0.20;
                            $middleNudgePx = $fontSizePxActual * 0.20;
                            [$wrapTop, $wrapTransform] = match ($el['vertical_align'] ?? 'middle') {
                                'top' => ['0%', 'none'],
                                'bottom' => ['100%', "translateY(-100%) translateY({$bottomNudgePx}px)"],
                                default => ['50%', "translateY(-50%) translateY(-{$middleNudgePx}px)"],
                            };
                            // resolved_line_height ya viene resuelto según
                            // line_height_rules (igual que resolved_font_size_px con
                            // font_size_rules) — solo cae al line_height fijo (o 1.15)
                            // si no hay reglas o ninguna matchea.
                            // word_spacing_px: espacio EXTRA (en px) que se suma entre
                            // palabras, además del espacio normal de la fuente.
                            $lineHeight = $el['resolved_line_height'] ?? $el['line_height'] ?? 1.15;
                            $wordSpacingPx = $el['word_spacing_px'] ?? 0;
                        @endphp
                        <div class="editor-text-wrap" style="
                            top: {{ $wrapTop }};
                            transform: {{ $wrapTransform }};
                            text-align: {{ $el['text_align'] ?? 'center' }};
                        ">
                            <p style="
                                font-family: '{{ $el['resolved_font_family'] ?? 'sans-serif' }}';
                                font-size: {{ $el['resolved_font_size_px'] ?? $el['font_size_px'] ?? 32 }}px;
                                font-weight: {{ $el['font_weight'] ?? 400 }};
                                line-height: {{ $lineHeight }};
                                word-spacing: {{ $wordSpacingPx }}px;
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
