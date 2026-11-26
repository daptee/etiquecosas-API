# Front: guardar DE QUÉ temática/posición sale un color o ícono elegido

Contexto completo en `PDF_TEMATICAS_EN_VIVO.md`. Resumen: el backend ya resuelve, en cada generación de PDF, el color/ícono REAL de una `PdfTheme` por posición (`theme_id` + `theme_color_index`/`theme_icon_index`) — si el admin edita la temática, todo lo que la referencia así se actualiza solo, sin tener que volver a guardar nada. El problema es que **hoy el editor no guarda esa referencia**: `themeColorHexes` (`composables/productPdfDesigns/useDesignEditor.ts`, línea ~366-376) aplana los colores de TODAS las temáticas asociadas en una sola lista de strings **sin duplicados** — para cuando un color llega al picker, ya se perdió de qué temática y de qué posición salió.

Sin este cambio, el campo nuevo del backend simplemente no se usa — nada se rompe, pero tampoco se gana nada.

## Qué hay que cambiar

**1. `composables/productPdfDesigns/useDesignEditor.ts`**

`themeColorHexes` (línea ~366) hoy es `string[]` aplanado y deduplicado. Para poder guardar la procedencia, tiene que dejar de deduplicar y pasar a ser algo como:

```ts
interface ThemeColorOption {
	hex: string;
	themeId: number;
	colorIndex: number; // posición DENTRO de theme.colors, la que espera el backend
}

const themeColorOptions = computed<ThemeColorOption[]>(() =>
	associatedThemes.value.flatMap(theme =>
		(theme.colors ?? []).map((hex, colorIndex) => ({ hex, themeId: theme.id, colorIndex }))
	)
);
```

(Puede convivir con el actual `themeColorHexes` si algún otro lugar todavía necesita la lista plana — o reemplazarlo si no.)

Para íconos, `themeIconIds` (línea ~358) ya devuelve solo ids (no hace falta la posición para que el PICKER filtre qué mostrar) — pero si además se quiere guardar `theme_icon_index` al elegir uno, hace falta algo similar: de qué temática y en qué posición está CADA id ofrecido, en vez de solo el Set final. Ojo: a diferencia del color, el ícono elegido YA es una referencia viva al catálogo (`icon_id`, `PersonalizationIcon`) — editar el ícono mismo (subirle otra imagen) ya se refleja solo, sin esto. `theme_icon_index` solo hace falta si además quieren que la temática pueda SACAR/REEMPLAZAR un ícono de su lista y que eso también se refleje — si no es un caso de uso real por ahora, se puede dejar para después sin perder nada de lo urgente (color).

**2. El picker de color del elemento** (`ElementConfig.vue`, la sección donde se ofrecen los colores de `themeColorHexes`/`editor.associatedThemes`)

Al elegir un color sugerido (no uno tipeado a mano), guardar:

```ts
update({
	color: {
		...element.color,
		mode: 'hex',
		value: option.hex,
		theme_id: option.themeId,
		theme_color_index: option.colorIndex
	}
});
```

Si el admin después tipea un hex a mano (no elige una sugerencia), hay que **sacar** `theme_id`/`theme_color_index` del objeto (poner `undefined`/omitirlos) — si no, el backend va a seguir pisando ese color con el de la temática en la próxima generación, ignorando lo que el admin tipeó a mano. Mismo cuidado para el color de `fill` de un fondo (`fill.color`, independiente de `color` — ver nota del backend: son dos objetos separados, cada uno con su propio `theme_id`/`theme_color_index`).

**3. `types/productPdfDesigns/IProductPdfDesign.ts`**

En el tipo de `color` (y en `PdfElementFill`'s color, si está tipado aparte), agregar:

```ts
theme_id?: number | null;
theme_color_index?: number | null;
```

Y en `PdfDesignElement` (para `icon`), junto a `icon_id`:

```ts
theme_id?: number | null; // ojo: incompatible por ahora con el theme_id de `color` si algún día conviven en el mismo elemento (no debería pasar: un icon no tiene `color`)
theme_icon_index?: number | null;
```

## Backend — ya está listo

- `EtiquetaService::resolverElementoDesign()` ya resuelve `color.theme_id`+`theme_color_index` y `icon` `theme_id`+`theme_icon_index` contra la `PdfTheme` en cada generación (ver `PDF_TEMATICAS_EN_VIVO.md`).
- `PdfDesignSanitizer` ya limpia/clampea ambos campos en `color` (de texto y de `fill`) y en elementos `icon` — no hace falta nada de validación extra del lado del front para que lleguen bien, solo mandarlos.
- Ejemplo de elemento ya funcional para probar sin esperar el picker nuevo (mandarlo a mano en el JSON del diseño vía Postman/curl):

```json
{
  "id": "texto1",
  "type": "text",
  "content": "EJEMPLO",
  "color": { "mode": "hex", "value": "#000000", "theme_id": 5, "theme_color_index": 1 }
}
```
