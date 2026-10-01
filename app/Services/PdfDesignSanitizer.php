<?php

namespace App\Services;

class PdfDesignSanitizer
{
    private const ALLOWED_SVG_TAGS = ['svg', 'path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'g'];
    private const ALLOWED_ELEMENT_TYPES = ['background', 'icon', 'text', 'shape'];
    private const ALLOWED_EDITABLE_FIELDS = ['text', 'color', 'icon'];
    private const ALLOWED_TEXT_ALIGN = ['left', 'center', 'right'];
    private const ALLOWED_VERTICAL_ALIGN = ['top', 'middle', 'bottom'];
    private const ALLOWED_DYNAMIC_FIELDS = ['nombre_apellido', 'nombre', 'apellido', 'fecha', 'numero_pedido'];
    private const ALLOWED_RADIUS_MODES = ['straight', 'rounded'];
    private const ALLOWED_LAYOUT_DIRECTIONS = ['vertical', 'horizontal'];
    private const ALLOWED_LAYOUT_ALIGNS = ['start', 'center', 'end'];
    private const ALLOWED_LAYOUT_MEMBER_TYPES = ['text', 'icon'];
    private const ALLOWED_LAYOUT_SIDES = ['after', 'before'];
    private const MAX_LAYOUT_GAP_CM = 50;

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

            if (isset($el['max_lines'])) {
                $el['max_lines'] = max(1, min(20, (int) $el['max_lines']));
            }
            if (isset($el['min_lines'])) {
                $el['min_lines'] = max(1, min(20, (int) $el['min_lines']));
            }
            if (isset($el['max_chars_per_line'])) {
                $el['max_chars_per_line'] = max(1, min(200, (int) $el['max_chars_per_line']));
            }
            $sanitizeRules = function ($rules) {
                return array_slice(array_map(function ($rule) {
                    return [
                        'max_chars' => isset($rule['max_chars']) ? max(0, (int) $rule['max_chars']) : null,
                        'font_size_px' => isset($rule['font_size_px']) ? max(1, min(500, (int) $rule['font_size_px'])) : null,
                    ];
                }, $rules), 0, 20);
            };
            if (!empty($el['font_size_rules']) && is_array($el['font_size_rules'])) {
                $el['font_size_rules'] = $sanitizeRules($el['font_size_rules']);
            }
            if (!empty($el['length_rules']) && is_array($el['length_rules'])) {
                $el['length_rules'] = $sanitizeRules($el['length_rules']);
            }
            if (isset($el['length_rules_enabled'])) {
                $el['length_rules_enabled'] = $el['length_rules_enabled'] === true;
            }
            if (!empty($el['line_height_rules']) && is_array($el['line_height_rules'])) {
                $el['line_height_rules'] = array_slice(array_map(function ($rule) {
                    return [
                        'max_chars' => isset($rule['max_chars']) ? max(0, (int) $rule['max_chars']) : null,
                        'line_height' => isset($rule['line_height']) ? max(0.5, min(5, (float) $rule['line_height'])) : null,
                    ];
                }, $el['line_height_rules']), 0, 20);
            }
            if (!empty($el['letter_spacing_rules']) && is_array($el['letter_spacing_rules'])) {
                $el['letter_spacing_rules'] = array_slice(array_map(function ($rule) {
                    return [
                        'max_chars' => isset($rule['max_chars']) ? max(0, (int) $rule['max_chars']) : null,
                        'letter_spacing_px' => isset($rule['letter_spacing_px']) ? max(-50, min(200, (float) $rule['letter_spacing_px'])) : null,
                    ];
                }, $el['letter_spacing_rules']), 0, 20);
            }

            if (isset($el['text_align']) && !in_array($el['text_align'], self::ALLOWED_TEXT_ALIGN, true)) {
                $el['text_align'] = 'center';
            }
            if (isset($el['vertical_align']) && !in_array($el['vertical_align'], self::ALLOWED_VERTICAL_ALIGN, true)) {
                $el['vertical_align'] = 'middle';
            }
            if (isset($el['dynamic_field']) && !in_array($el['dynamic_field'], self::ALLOWED_DYNAMIC_FIELDS, true)) {
                $el['dynamic_field'] = null;
            }
            if (isset($el['value_mode']) && is_string($el['value_mode'])) {
                $el['value_mode'] = strip_tags($el['value_mode']);
            }
            if (isset($el['rotation_deg'])) {
                $el['rotation_deg'] = max(-360, min(360, (float) $el['rotation_deg']));
            }
            if (isset($el['vertical_offset_cm'])) {
                $el['vertical_offset_cm'] = max(-50, min(50, (float) $el['vertical_offset_cm']));
            }
            if (isset($el['padding_cm'])) {
                $el['padding_cm'] = max(0, min(50, (float) $el['padding_cm']));
            }
            if (isset($el['font_weight'])) {
                $el['font_weight'] = max(100, min(900, (int) $el['font_weight']));
            }
            if (isset($el['line_height'])) {
                $el['line_height'] = max(0.5, min(5, (float) $el['line_height']));
            }
            if (isset($el['letter_spacing_px'])) {
                $el['letter_spacing_px'] = max(-50, min(200, (float) $el['letter_spacing_px']));
            }
            if (isset($el['group_id']) && is_string($el['group_id'])) {
                $el['group_id'] = strip_tags($el['group_id']);
            }
            if (isset($el['radius_mode']) && !in_array($el['radius_mode'], self::ALLOWED_RADIUS_MODES, true)) {
                $el['radius_mode'] = 'straight';
            }
            if (isset($el['radius_pct'])) {
                $el['radius_pct'] = max(0, min(100, (float) $el['radius_pct']));
            }
            if (isset($el['border']['width_cm'])) {
                $el['border']['width_cm'] = max(0, min(5, (float) $el['border']['width_cm']));
            }
            if (isset($el['background_image']) && is_string($el['background_image'])) {
                $el['background_image'] = strip_tags($el['background_image']);
            }
            if (isset($el['custom_icon_path']) && is_string($el['custom_icon_path'])) {
                $el['custom_icon_path'] = strip_tags($el['custom_icon_path']);
            }

            return $el;
        }, $elements);
    }

    /**
     * Sanea data.pages[].sheet: color y ruta de imagen de fondo de la hoja.
     * La ruta de la imagen nunca se acepta como URL/HTML libre — tiene que
     * venir del endpoint de subida (POST .../background-image), así que acá
     * solo se limpia texto ejecutable, no se valida que el archivo exista.
     */
    public static function sanitizeSheet(array $sheet): array
    {
        if (isset($sheet['background_color']) && is_array($sheet['background_color'])) {
            $mode = $sheet['background_color']['mode'] ?? 'hex';
            $sheet['background_color'] = [
                'mode' => in_array($mode, ['hex', 'cmyk'], true) ? $mode : 'hex',
                'value' => isset($sheet['background_color']['value']) ? strip_tags((string) $sheet['background_color']['value']) : null,
            ];
        }

        if (isset($sheet['background_image']) && is_string($sheet['background_image'])) {
            $sheet['background_image'] = strip_tags($sheet['background_image']);
        }

        return $sheet;
    }

    /**
     * Sanea data.pages[].layout_groups: solo deja pasar los campos conocidos
     * (v2: links/anchor; modelo 1: direction/gap_cm/align), con los ids
     * limpios y los números como números. No decide si el grupo es válido —
     * eso lo hace validateLayoutGroups() (422 al guardar, descarte con
     * warning al generar).
     */
    public static function sanitizeLayoutGroups(array $groups): array
    {
        $cleanId = fn($id) => is_scalar($id) && (string) $id !== '' ? strip_tags((string) $id) : null;
        $number = fn($value) => is_numeric($value) ? (float) $value : $value;

        $cleanRule = function ($rule) use ($number) {
            if (!is_array($rule)) {
                return $rule;
            }
            $clean = ['mode' => $rule['mode'] ?? null];
            if (array_key_exists('side', $rule)) {
                $clean['side'] = $rule['side'];
            }
            if (array_key_exists('cm', $rule)) {
                $clean['cm'] = $number($rule['cm']);
            }
            if (array_key_exists('value', $rule)) {
                $clean['value'] = $rule['value'];
            }
            return $clean;
        };

        return array_values(array_map(function ($group) use ($cleanId, $number, $cleanRule) {
            if (!is_array($group)) {
                return $group;
            }

            $clean = [
                'id' => $cleanId($group['id'] ?? null),
                'members' => is_array($group['members'] ?? null)
                    ? array_values(array_map(fn($id) => is_scalar($id) ? strip_tags((string) $id) : $id, $group['members']))
                    : ($group['members'] ?? null),
                'container_element_id' => $cleanId($group['container_element_id'] ?? null),
            ];

            if (array_key_exists('links', $group) && $group['links'] !== null) {
                $clean['links'] = is_array($group['links'])
                    ? array_values(array_map(fn($link) => is_array($link)
                        ? ['x' => $cleanRule($link['x'] ?? null), 'y' => $cleanRule($link['y'] ?? null)]
                        : $link, $group['links']))
                    : $group['links'];
            }
            if (array_key_exists('anchor', $group) && $group['anchor'] !== null) {
                $clean['anchor'] = is_array($group['anchor'])
                    ? ['x_cm' => $number($group['anchor']['x_cm'] ?? null), 'y_cm' => $number($group['anchor']['y_cm'] ?? null)]
                    : $group['anchor'];
            }

            // Modelo 1 (compatibilidad): solo se usan si no hay links.
            foreach (['direction', 'gap_cm', 'align'] as $field) {
                if (array_key_exists($field, $group)) {
                    $clean[$field] = $field === 'gap_cm' ? $number($group[$field]) : $group[$field];
                }
            }

            return $clean;
        }, $groups));
    }

    /**
     * Valida los layout_groups de UNA página contra sus elementos. Devuelve
     * [índice del grupo => mensaje] solo para los grupos inválidos (vacío =
     * todo bien).
     *
     * $alGuardar = true (POST del diseño, cada error es un 422): un
     * container_element_id que no existe o que es miembro del grupo es error.
     * $alGuardar = false (al generar el PDF, datos guardados antes): ese
     * contenedor se ignora y se usa anchor; solo es error si tampoco hay anchor.
     *
     * Un miembro repetido en dos grupos es error para el SEGUNDO grupo (al
     * generar, el primero lo conserva).
     */
    public static function validateLayoutGroups(array $groups, array $elements, bool $alGuardar = true): array
    {
        $elementsById = [];
        foreach ($elements as $el) {
            if (is_array($el) && isset($el['id']) && is_scalar($el['id']) && $el['id'] !== '') {
                $elementsById[(string) $el['id']] ??= $el;
            }
        }

        $errors = [];
        $seenGroupIds = [];
        $usedMembers = [];

        foreach ($groups as $idx => $group) {
            if (!is_array($group)) {
                $errors[$idx] = 'El grupo tiene que ser un objeto.';
                continue;
            }

            $groupId = $group['id'] ?? null;
            if (!is_scalar($groupId) || (string) $groupId === '') {
                $errors[$idx] = 'Falta el id del grupo.';
                continue;
            }
            $groupId = (string) $groupId;
            if (isset($seenGroupIds[$groupId])) {
                $errors[$idx] = "El id de grupo \"{$groupId}\" está repetido en la página.";
                continue;
            }
            $seenGroupIds[$groupId] = true;

            $error = self::validarMiembros($group, $groupId, $elementsById, $usedMembers)
                ?? self::validarReglasLayout($group, $groupId)
                ?? self::validarCentrado($group, $groupId, $elementsById, $alGuardar);

            if ($error) {
                $errors[$idx] = $error;
                continue;
            }

            foreach ($group['members'] as $memberId) {
                $usedMembers[(string) $memberId] = $groupId;
            }
        }

        return $errors;
    }

    private static function validarMiembros(array $group, string $groupId, array $elementsById, array $usedMembers): ?string
    {
        $members = $group['members'] ?? null;
        if (!is_array($members) || count($members) === 0) {
            return "Grupo \"{$groupId}\": members no puede estar vacío.";
        }

        $groupMembers = [];
        foreach ($members as $memberId) {
            $memberKey = is_scalar($memberId) ? (string) $memberId : null;
            $member = $memberKey !== null ? ($elementsById[$memberKey] ?? null) : null;
            if (!$member) {
                return "Grupo \"{$groupId}\": el miembro \"" . ($memberKey ?? '?') . '" no existe en la página.';
            }
            if (!in_array($member['type'] ?? null, self::ALLOWED_LAYOUT_MEMBER_TYPES, true)) {
                return "Grupo \"{$groupId}\": el miembro \"{$memberKey}\" tiene que ser de tipo text o icon.";
            }
            if (isset($usedMembers[$memberKey]) || isset($groupMembers[$memberKey])) {
                $otherGroup = $usedMembers[$memberKey] ?? $groupId;
                return "Grupo \"{$groupId}\": el elemento \"{$memberKey}\" ya está en el grupo \"{$otherGroup}\".";
            }
            $groupMembers[$memberKey] = true;
        }

        return null;
    }

    /**
     * Con links (v2) valida cada regla por eje; sin links, los campos del
     * modelo 1 (direction/align/gap_cm). Si hay links, el modelo 1 se ignora.
     */
    private static function validarReglasLayout(array $group, string $groupId): ?string
    {
        if (array_key_exists('links', $group)) {
            $links = $group['links'];
            $expected = count($group['members']) - 1;
            if (!is_array($links) || count($links) !== $expected) {
                return "Grupo \"{$groupId}\": links tiene que tener {$expected} elemento(s) (uno menos que members).";
            }

            foreach (array_values($links) as $linkIdx => $link) {
                foreach (['x', 'y'] as $axis) {
                    $ruleError = self::validarReglaEje(is_array($link) ? ($link[$axis] ?? null) : null);
                    if ($ruleError) {
                        return "Grupo \"{$groupId}\": links[{$linkIdx}].{$axis} {$ruleError}";
                    }
                }
            }

            return null;
        }

        if (!in_array($group['direction'] ?? null, self::ALLOWED_LAYOUT_DIRECTIONS, true)) {
            return "Grupo \"{$groupId}\": direction tiene que ser vertical u horizontal (o mandar links).";
        }
        if (!in_array($group['align'] ?? 'center', self::ALLOWED_LAYOUT_ALIGNS, true)) {
            return "Grupo \"{$groupId}\": align tiene que ser start, center o end.";
        }
        $gap = $group['gap_cm'] ?? null;
        if (!is_numeric($gap) || (float) $gap < 0 || (float) $gap > self::MAX_LAYOUT_GAP_CM) {
            return "Grupo \"{$groupId}\": gap_cm tiene que ser un número entre 0 y " . self::MAX_LAYOUT_GAP_CM . '.';
        }

        return null;
    }

    private static function validarReglaEje($rule): ?string
    {
        if (!is_array($rule)) {
            return 'falta o no es un objeto.';
        }

        return match ($rule['mode'] ?? null) {
            'gap' => match (true) {
                !in_array($rule['side'] ?? null, self::ALLOWED_LAYOUT_SIDES, true) => 'tiene un side inválido (after o before).',
                !is_numeric($rule['cm'] ?? null) || !is_finite((float) $rule['cm']) || abs((float) $rule['cm']) > self::MAX_LAYOUT_GAP_CM
                    => 'tiene que tener cm entre -' . self::MAX_LAYOUT_GAP_CM . ' y ' . self::MAX_LAYOUT_GAP_CM . '.',
                default => null,
            },
            'align' => in_array($rule['value'] ?? null, self::ALLOWED_LAYOUT_ALIGNS, true)
                ? null
                : 'tiene un value inválido (start, center o end).',
            default => 'tiene un mode inválido (gap o align).',
        };
    }

    /**
     * Punto de centrado: el centro de container_element_id (cualquier elemento
     * que no sea miembro) o, si no, anchor.
     */
    private static function validarCentrado(array $group, string $groupId, array $elementsById, bool $alGuardar): ?string
    {
        $anchor = $group['anchor'] ?? null;
        $hasAnchor = $anchor !== null;
        if ($hasAnchor && !self::esAnchorValido($anchor)) {
            return "Grupo \"{$groupId}\": anchor tiene que tener x_cm e y_cm numéricos.";
        }

        $containerId = $group['container_element_id'] ?? null;
        if ($containerId !== null && $containerId !== '') {
            $containerError = null;
            if (!isset($elementsById[(string) $containerId])) {
                $containerError = "Grupo \"{$groupId}\": el container_element_id \"{$containerId}\" no existe en la página.";
            } elseif (in_array((string) $containerId, array_map('strval', $group['members']), true)) {
                $containerError = "Grupo \"{$groupId}\": el container_element_id no puede ser uno de los members.";
            }

            if (!$containerError) {
                return null;
            }
            if ($alGuardar || !$hasAnchor) {
                return $containerError;
            }
        }

        if (!$hasAnchor) {
            return "Grupo \"{$groupId}\": falta anchor o container_element_id para saber dónde centrar.";
        }

        return null;
    }

    public static function esAnchorValido($anchor): bool
    {
        return is_array($anchor)
            && is_numeric($anchor['x_cm'] ?? null) && is_finite((float) $anchor['x_cm'])
            && is_numeric($anchor['y_cm'] ?? null) && is_finite((float) $anchor['y_cm']);
    }
}
