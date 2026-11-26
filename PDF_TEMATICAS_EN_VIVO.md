# Temáticas: editar una ya no requiere tocar los diseños que la usan

Complementa `PDF_TEMATICAS.md`. Hasta ahora, `GET /pdf-themes/suggest` era **solo una sugerencia**: el editor mostraba los colores/íconos de la temática asociada al producto/variante, pero el valor elegido se guardaba como un dato suelto (`color.value: "#FF0000"`, `icon_id: 74`) **sin ninguna referencia a la temática** — editar la temática después no cambiaba nada en los diseños que ya la habían usado.

## Cómo funciona ahora

Un `color` (de un texto o de `fill` de un fondo) puede traer, además de `mode`/`value`:

```json
"color": {
  "mode": "hex",
  "value": "#FF0000",
  "theme_id": 5,
  "theme_color_index": 1
}
```

Un elemento `type: "icon"` puede traer, junto a `icon_id`:

```json
{
  "type": "icon",
  "icon_id": 74,
  "theme_id": 5,
  "theme_icon_index": 2
}
```

`value`/`icon_id` se siguen guardando (son el fallback), pero **en cada generación de PDF** (`EtiquetaService::resolverElementoDesign()`), si `theme_id` + `theme_color_index`/`theme_icon_index` están presentes:

- Color: se busca `PdfTheme::find(theme_id)->colors[theme_color_index]` y, si existe, PISA `value` con ese hex — el que tenga la temática **hoy**, no el que tenía cuando se guardó el diseño.
- Ícono: se busca `PdfTheme::find(theme_id)->icons[theme_icon_index]` (ordenados por `sort_order`, igual que ya devuelve `PdfTheme::icons()`) y, si existe, PISA `icon_id` con el de esa posición — de ahí en más sigue exactamente la misma resolución de ícono de siempre (ícono por atributo de variante > ícono libre del cliente > imagen propia subida > `icon_id`), solo que el `icon_id` "de catálogo fijo" que compite ahí ya viene actualizado.

Si la temática se borró o el índice ya no existe (se achicó la paleta/lista de íconos), **no es un error** — se usa el `value`/`icon_id` baked tal cual quedó la última vez. Mismo criterio de "nunca romper, degradar con gracia" que ya usa `dynamic_attribute_id` para ícono/tipografía por atributo de variante — de hecho es el MISMO patrón, aplicado a temáticas: una referencia que se resuelve en vivo en vez de un valor congelado.

## Por qué así y no con un endpoint de propagación

La alternativa considerada (y descartada) era un endpoint al que mandarle `{themeId, nuevaConfig}` y que busque y reescriba todos los `product_pdf_designs` que usan esa temática. Se eligió resolver en vivo porque:

- Es menos código: no hay que "encontrar todos los diseños que la usan" en ningún lado — la resolución es local a cada elemento, en el mismo punto donde ya se resuelve todo lo demás.
- No hay riesgo de que una corrida de propagación se olvide un diseño, falle a la mitad, o deje una copia vieja en un PDF ya generado antes de correrla.
- Ya es el patrón que usa este mismo archivo para `dynamic_attribute_id` (ícono/tipografía "por atributo de variante") — coherente con el resto del sistema, no una excepción nueva.

La única cosa que esto NO soluciona sola: un PDF **acumulable** que ya se generó y quedó guardado en disco (el del día) no se actualiza solo — recién se corrige la próxima vez que se regenere (se agrega un ítem nuevo, o se corre `GenerarEtiquetas`/`AccumulatingPdfDesignService` de nuevo para ese día).

## Verificado

Con `ReflectionMethod` sobre `resolverElementoDesign()`, con una `PdfTheme` real (2 colores, 2 íconos):

- Texto con `color.theme_color_index: 1` y fondo con `fill.color.theme_color_index: 0` resolvieron los hex correctos de la temática.
- Ícono con `theme_icon_index: 1` resolvió el `icon_id` correcto (pisando un `icon_id` baked viejo).
- Al editar la temática (cambiar los colores Y reordenar los íconos) y volver a resolver los MISMOS elementos (sin tocarlos para nada), los tres reflejaron los valores nuevos automáticamente.
- Al borrar (soft-delete) la temática, la resolución volvió a usar el `value`/`icon_id` baked original sin errores.

## Pendiente del front

Ver `PDF_TEMATICAS_EN_VIVO_FRONT.md` — el backend ya está listo, pero el editor hoy ni siquiera guarda `theme_id`/`theme_color_index` (la lista de colores sugeridos se muestra ya aplanada/sin duplicados, sin rastro de qué temática/posición es cada uno) y `GET /pdf-themes/suggest` sigue documentado como "solo sugerencia" — hay que decidir ahí cómo se arma la UI para que el admin pueda elegir "este color ES de la temática" en vez de "este color se parece a uno de la temática".
