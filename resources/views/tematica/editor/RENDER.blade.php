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

{{-- Fuente de respaldo de todos los textos (y la de font_id vacío):
     métricas de Arial, que es lo que dibuja el editor. Se declara para 400 y
     700 con el mismo archivo para que un font_weight distinto no caiga en
     Times-Bold. --}}
@php
    $fallbackFontFamily = \App\Services\EtiquetaService::FALLBACK_FONT_FAMILY;
    $fallbackFontPath = str_replace('\\', '/', public_path(\App\Services\EtiquetaService::FALLBACK_FONT_FILE));
@endphp
@foreach ([400, 700] as $fallbackWeight)
@font-face {
    font-family: '{{ $fallbackFontFamily }}';
    src: url('file://{{ $fallbackFontPath }}') format('truetype');
    font-weight: {{ $fallbackWeight }};
}
@endforeach

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
    @php
        $sheetBgColor = $page['sheet']['background_color'] ?? null;
        $sheetBgColorCss = $sheetBgColor
            ? (($sheetBgColor['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . $sheetBgColor['value'] . ')' : ($sheetBgColor['value'] ?? null))
            : null;
        $sheetBgImagePath = $page['sheet']['background_image'] ?? null;
    @endphp
    <div class="editor-sheet" style="
        width: {{ $page['sheet']['width_cm'] ?? 18.5 }}cm;
        height: {{ $page['sheet']['height_cm'] ?? 29 }}cm;
        @if ($sheetBgColorCss) background-color: {{ $sheetBgColorCss }}; @endif
        @if (!$loop->last) page-break-after: always; @endif
    ">
        @if ($sheetBgImagePath)
            {{-- Fondo de página completa: va primero en el DOM y con z-index
                 bajo para quedar detrás de todos los elementos, sean cuales
                 sean sus propios z-index (0, 1, 2...). --}}
            <img src="file://{{ str_replace('\\', '/', public_path($sheetBgImagePath)) }}" style="
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                z-index: -1;
                object-fit: cover;
            ">
        @endif
        @foreach ($page['elements'] as $el)
            {{-- Miembro ausente de un layout_group (ícono sin imagen, texto vacío). --}}
            @continue(!empty($el['layout_hidden']))
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
                            $backgroundImagePath = $el['background_image'] ?? null;
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
                            ">
                                @if ($backgroundImagePath)
                                    {{-- Imagen propia de ESTA etiqueta (no toda la página) —
                                         reemplaza el color de fondo. Va adentro del mismo div
                                         con overflow:hidden/border-radius de arriba, así que
                                         respeta el recorte/esquinas igual que el color. --}}
                                    <img src="file://{{ str_replace('\\', '/', public_path($backgroundImagePath)) }}" style="
                                        width: 100%;
                                        height: 100%;
                                        object-fit: cover;
                                    ">
                                @endif
                            </div>
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
                            // "middle": SIN empujón. Con el ratio de obtenerRatioMetricasFuente()
                            // ya aplicado (resolved_line_height), el alto de línea efectivo que
                            // dompdf dibuja es line_height×font_size exacto — y con ese alto, el
                            // tramo de tinta real (de la punta del ascendente de la 1ra línea a
                            // la punta del descendente de la última) queda centrado matemáticamente
                            // en la caja SOLO con translateY(-50%): el half-leading que CSS reparte
                            // arriba/abajo de cada renglón ya es simétrico, así que agregar un
                            // empujón fijo (0.20×font_size, probado antes) lo descentra para cajas
                            // chicas/line_height ajustado — medido con un caso real (etiqueta de
                            // 1.15cm con 2 renglones, line_height:1): con el empujón el texto
                            // quedaba pegado arriba (separación 1:2.6 entre arriba/abajo); sin él,
                            // la separación da prácticamente simétrica.
                            $fontSizePxActual = $el['resolved_font_size_px'] ?? $el['font_size_px'] ?? 32;
                            $bottomNudgePx = $fontSizePxActual * 0.20;
                            // "top": dompdf deja un espacio invisible ENCIMA del primer
                            // renglón (ascenso de la fuente + el medio-interlineado de la
                            // caja de línea) que un navegador no muestra igual — el editor
                            // dibuja el texto pegado al borde superior de su caja, así que
                            // hay que subirlo para compensar ese aire, si no queda más
                            // abajo de lo esperado. Medido contra un caso real (Oswald,
                            // 16px, line_height 1.15): ese aire ronda el 90% del tamaño de
                            // fuente — se deja un poco conservador (85%) de margen.
                            $topNudgePx = $fontSizePxActual * -0.10;
                            $topShiftPx = -$topNudgePx;
                            [$wrapTop, $wrapTransform] = match ($el['vertical_align'] ?? 'middle') {
                                'top' => ['0%', "translateY({$topShiftPx}px)"],
                                'bottom' => ['100%', "translateY(-100%) translateY({$bottomNudgePx}px)"],
                                default => ['50%', 'translateY(-50%)'],
                            };
                            // Texto dentro de un layout_group: la caja ya mide
                            // exactamente renglones × font_size × line_height (lo
                            // calculó aplicarLayoutGroups), así que va pegado arriba,
                            // sin vertical_align ni empujones, y sin que dompdf lo
                            // vuelva a cortar por ancho.
                            $isLayoutText = !empty($el['layout_text']);
                            if ($isLayoutText) {
                                [$wrapTop, $wrapTransform] = ['0', 'none'];
                            }
                            // resolved_line_height ya viene resuelto según
                            // line_height_rules (igual que resolved_font_size_px con
                            // font_size_rules) — solo cae al line_height fijo (o 1.15)
                            // si no hay reglas o ninguna matchea.
                            // resolved_letter_spacing_px ya viene resuelto según
                            // letter_spacing_rules (igual que resolved_line_height con
                            // line_height_rules) — solo cae al letter_spacing_px fijo
                            // (o 0) si no hay reglas o ninguna matchea.
                            $lineHeight = $el['resolved_line_height'] ?? $el['line_height'] ?? 1.15;
                            $letterSpacingPx = $el['resolved_letter_spacing_px'] ?? $el['letter_spacing_px'] ?? 0;
                        @endphp
                        <div class="editor-text-wrap" style="
                            top: {{ $wrapTop }};
                            transform: {{ $wrapTransform }};
                            text-align: {{ $el['text_align'] ?? 'center' }};
                        ">
                            <p style="
                                font-family: @if (!empty($el['resolved_font_family']))'{{ $el['resolved_font_family'] }}', @endif'{{ $fallbackFontFamily }}';
                                @if ($isLayoutText) white-space: nowrap; @endif
                                font-size: {{ $el['resolved_font_size_px'] ?? $el['font_size_px'] ?? 32 }}px;
                                font-weight: {{ $el['font_weight'] ?? 400 }};
                                line-height: {{ $lineHeight }};
                                letter-spacing: {{ $letterSpacingPx }}px;
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
