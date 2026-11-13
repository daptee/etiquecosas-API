# Tamaño del ícono según la longitud del texto agrupado

Pedido: en una etiqueta tipo `database/json-pdf/Etiquetas banda.json` (un texto de nombre + un ícono, agrupados con `group_id`), si el nombre es largo el ícono tiene que achicarse para no pisarlo — igual que ya existe para el tamaño de fuente del texto (`font_size_rules`, ver `PDF_EDITOR.md`) y para el interlineado/espaciado (`PDF_INTERLINEADO_ESPACIADO.md`).

Ya existía `icon_scale_pct`: dibuja el ícono a ese % de su propio bloque, centrado, sin mover ni achicar el bloque (ver `PDF_ESCALA_ICONO_Y_COLOR_DE_LA_COMPRA.md`). Esta feature agrega una tabla de reglas que elige **ese mismo** `icon_scale_pct` según cuántos caracteres tiene el texto agrupado con el ícono.

## Campo nuevo: `icon_size_rules` (elemento `type: "icon"`)

```json
{
  "id": "icono1",
  "type": "icon",
  "group_id": "group-1794553276369-7-1610",
  "icon_scale_pct": 100,
  "icon_size_rules": [
    { "max_chars": 4, "icon_scale_pct": 100 },
    { "max_chars": 10, "icon_scale_pct": 60 },
    { "max_chars": null, "icon_scale_pct": 30 }
  ]
}
```

- Lista ordenada por `max_chars` (de menor a mayor); se usa la primera regla cuyo `max_chars` sea mayor o igual a la cantidad de caracteres del texto agrupado. Una regla con `max_chars: null` es "para el resto" — va al final.
- Cada `icon_scale_pct` se clampea 10-100, igual que el campo suelto.
- **Solo tiene efecto si este ícono comparte `group_id` con un elemento `type: "text"`** en la misma página/celda — el largo que se usa es el de ESE texto ya resuelto (nombre/apellido real del cliente, no el texto de ejemplo del editor). Si el ícono no está agrupado con ningún texto (sin `group_id`, o agrupado solo con otro ícono/fondo), `icon_size_rules` se ignora por completo y se usa el `icon_scale_pct` fijo de siempre.
- Si no se manda `icon_size_rules` (o ninguna regla matchea), también se usa el `icon_scale_pct` fijo — total compatibilidad con diseños existentes (ninguno tiene este campo hoy).
- Si hay más de un texto en el mismo grupo (no debería pasar en un uso normal: un grupo = una etiqueta con un nombre y un ícono), se usa el primero que aparece en la lista de elementos de la página.

## Dónde se resolvió (backend)

- `EtiquetaService::resolverEscalaIcono()` — misma lógica de matching que `resolverTamanoFuente()`/`resolverInterlineado()`/`resolverEspaciadoLetras()`, pero el largo no sale del propio elemento (un ícono no tiene texto) sino que se lo pasa `ajustarTamanoIconoPorTexto()`.
- `EtiquetaService::ajustarTamanoIconoPorTexto()` — agrupa los elementos ya resueltos de la página/celda por `group_id`, toma el largo del texto de cada grupo y, para cada ícono agrupado con `icon_size_rules`, calcula `resolved_icon_scale_pct`. Se llama después de `ajustarPorIconoAusente()` tanto en `generarEtiquetasDesdeDesign()` (diseño normal, línea ~613) como en `resolverCeldaAcumulada()` (diseños acumulables tipo Bandas).
- `RENDER.blade.php` (case `icon`): usa `resolved_icon_scale_pct ?? icon_scale_pct ?? 100` — la escala calculada por longitud tiene prioridad sobre la fija cuando está disponible.
- `PdfDesignSanitizer::sanitizeElements()`: sanea `icon_size_rules` (clamp de `max_chars`/`icon_scale_pct`, máx. 20 reglas) igual que `font_size_rules`, y guarda el estado editable del editor (`icon_length_rules`/`icon_length_rules_enabled`) sin tocarlo, para que el front pueda ida-y-vuelta igual que ya hace con `length_rules`/`length_rules_enabled`.

## Verificación

Probado con `ReflectionMethod` sobre `ajustarTamanoIconoPorTexto()` (un ícono agrupado con reglas cambia de escala según el largo del texto del grupo; un ícono sin `group_id` o agrupado sin reglas no se toca) y con un PDF real generado con `generarEtiquetasDesdeDesign()`: mismo ícono, mismo bloque de 3x3cm, "Ana" (3 caracteres) salió al 100% y "Guillerminaaa" (13 caracteres) salió al 30%, centrado en el mismo lugar.

## Pendiente del front

Ver `PDF_ESCALA_ICONO_POR_TEXTO_FRONT.md` (doc aparte, para mandarle directo al equipo de front) — agrega la UI editable y el envío de `icon_size_rules` al guardar. Sin eso, el campo ya funciona si se manda a mano (ej. por Postman) pero no hay forma de configurarlo desde el editor.
