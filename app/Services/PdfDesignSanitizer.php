<?php

namespace App\Services;

class PdfDesignSanitizer
{
    private const ALLOWED_SVG_TAGS = ['svg', 'path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'g'];
    private const ALLOWED_ELEMENT_TYPES = ['background', 'icon', 'text', 'shape'];
    private const ALLOWED_EDITABLE_FIELDS = ['text', 'color', 'icon'];

    /**
     * Deja pasar únicamente primitivos de forma SVG. Rechaza cualquier otra
     * etiqueta (script, foreignObject, style, etc.) y cualquier atributo on*=
     * o href/xlink:href, para que nunca se persista markup ejecutable.
     */
    public static function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('/<!--.*?-->/s', '', $svg) ?? '';

        return preg_replace_callback('/<\/?([a-zA-Z0-9]+)([^>]*)>/', function ($m) {
            $tag = strtolower($m[1]);
            if (!in_array($tag, self::ALLOWED_SVG_TAGS, true)) {
                return '';
            }

            $isClosing = str_starts_with($m[0], '</');
            if ($isClosing) {
                return "</{$tag}>";
            }

            $attrs = $m[2] ?? '';
            $cleanAttrs = '';
            if (preg_match_all('/([a-zA-Z0-9_:-]+)\s*=\s*"([^"]*)"/', $attrs, $attrMatches, PREG_SET_ORDER)) {
                foreach ($attrMatches as $attrMatch) {
                    $attrName = strtolower($attrMatch[1]);
                    if (str_starts_with($attrName, 'on') || in_array($attrName, ['href', 'xlink:href'], true)) {
                        continue;
                    }
                    $cleanAttrs .= " {$attrName}=\"{$attrMatch[2]}\"";
                }
            }

            $selfClosing = str_ends_with(trim($m[0]), '/>');
            return "<{$tag}{$cleanAttrs}" . ($selfClosing ? ' />' : '>');
        }, $svg);
    }

    /**
     * Sanea la lista de elementos del diseño (product_pdf_designs.data.elements):
     * texto sin HTML (solo placeholders de texto plano) y solo tipos/campos
     * editables reconocidos. Nunca deja pasar markup ejecutable desde el front.
     */
    public static function sanitizeElements(array $elements): array
    {
        return array_map(function ($el) {
            if (!is_array($el)) {
                return $el;
            }

            if (isset($el['type']) && !in_array($el['type'], self::ALLOWED_ELEMENT_TYPES, true)) {
                $el['type'] = 'text';
            }

            if (isset($el['content']) && is_string($el['content'])) {
                $el['content'] = strip_tags($el['content']);
            }

            if (isset($el['editable_field']) && !in_array($el['editable_field'], self::ALLOWED_EDITABLE_FIELDS, true)) {
                $el['editable_field'] = null;
            }

            $el['editable_by_customer'] = ($el['editable_by_customer'] ?? false) === true;

            return $el;
        }, $elements);
    }
}
