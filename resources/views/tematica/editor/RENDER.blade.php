<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
{!! file_get_contents(public_path('css/etiquetas.css')) !!}

@foreach ($plantilla['design']['elements'] as $el)
    @if (($el['type'] ?? null) === 'text' && !empty($el['resolved_font_family']) && !empty($el['resolved_font_files'][0]))
        @font-face {
            font-family: '{{ $el['resolved_font_family'] }}';
            src: url('{{ $el['resolved_font_files'][0] }}');
        }
    @endif
@endforeach

.editor-sheet {
    position: relative;
    width: {{ $plantilla['design']['sheet']['width_cm'] ?? 18.5 }}cm;
    height: {{ $plantilla['design']['sheet']['height_cm'] ?? 29 }}cm;
}
.editor-element {
    position: absolute;
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
<div class="editor-sheet">
    @foreach ($plantilla['design']['elements'] as $el)
        <div class="editor-element" style="
            left: {{ $el['x_cm'] ?? 0 }}cm;
            top: {{ $el['y_cm'] ?? 0 }}cm;
            width: {{ $el['width_cm'] ?? 1 }}cm;
            height: {{ $el['height_cm'] ?? 1 }}cm;
            z-index: {{ $el['z_index'] ?? 0 }};
        ">
            @switch($el['type'] ?? null)
                @case('background')
                    <div style="width:100%; height:100%; background: {{ ($el['color']['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . $el['color']['value'] . ')' : ($el['color']['value'] ?? '#FFFFFF') }};"></div>
                    @break

                @case('icon')
                    @if (!empty($el['resolved_icon_path']))
                        <img src="{{ $el['resolved_icon_path'] }}" style="width:100%; height:100%;">
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
</body>
</html>
