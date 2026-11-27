> **Superado.** El back cambió de enfoque (resolución en vivo → propagación con persistencia). Ver
> `PDF_VARIANTES_ETIQUETA_PROPAGACION.md` (su propia propuesta) y `PDF_VARIANTES_ETIQUETA_PROPAGACION_BACK.md`
> (confirmación e implementación) para lo que hay que hacer ahora. Nada de lo que describe este
> documento (`variant_id` suelto, resolución en vivo en `CanvasArea.vue`) hace falta implementarlo.

# Front: guardar de qué variante sale una etiqueta, y resolverla en vivo en el editor

Contexto completo en `PDF_VARIANTES_ETIQUETA_EN_VIVO.md`. Resumen: el backend ya reconstruye, en cada
generación de PDF, cualquier etiqueta (`background` con `label_shape_id`) que tenga además `variant_id`
— usa la definición ACTUAL de esa variante (`label_shapes.data.variants[]`), pisando lo que haya
guardado. Si el admin edita la variante, todo lo que la referencia así se actualiza solo. Para esto
hacen falta dos cosas del lado del front, y **van juntas** (mismo problema que ya pasó con las
temáticas, ver `PDF_TEMATICAS_EN_VIVO_APLICADO.md`): guardar la referencia sin que el preview del
editor también la resuelva deja al admin viendo una cosa en pantalla y otra distinta al descargar.

## 1. Guardar `variant_id` al aplicar una variante

Un solo lugar lo cubre todo: `instantiateLabelVariant` en `utils/LabelShapeVariantsHelper.ts` (línea
~191), usada tanto por `onInsertLabelVariant` como por `onApplyLabelVariant`
(`composables/productPdfDesigns/useDesignEditor.ts`).

```ts
// ANTES
const label: PdfDesignElement = {
	...place(variant.label),
	type: 'background',
	label_shape_id: shape.id,
	width_cm: shape.widthCm,
	height_cm: shape.heightCm
};
```

```ts
// DESPUÉS
const label: PdfDesignElement = {
	...place(variant.label),
	type: 'background',
	label_shape_id: shape.id,
	variant_id: variant.id,
	width_cm: shape.widthCm,
	height_cm: shape.heightCm
};
```

Y en `types/productPdfDesigns/IProductPdfDesign.ts`, junto a `label_shape_id` en `PdfDesignElement`:

```ts
variant_id?: string | null;
```

Con esto alcanza para que el PDF y el editor AL REABRIR el diseño (`show()`, que ya resuelve en el
backend) queden al día. Nada más que guardar — no hace falta ningún botón de "actualizar variante": es
automático, igual que las temáticas.

## 2. El canvas del editor tiene que resolver la misma referencia, en vivo

Sin esto, si el admin edita la variante DESDE la misma sesión en la que tiene abierto un diseño que la
usa (o simplemente vuelve a esa pestaña sin recargar), el canvas sigue mostrando la etiqueta vieja hasta
que se reabre el diseño — mismo problema que ya se encontró con las temáticas.

**a. Nueva función en `utils/LabelShapeVariantsHelper.ts`** (reusa `elementsOnLabel`,
`instantiateLabelVariant`, `rotatePlacedLabelVariant` y la `layoutGroupsWithin` ya privada del mismo
archivo — no hace falta exportarla, esta función vive en el mismo módulo):

```ts
// Resuelve en vivo, para DIBUJAR únicamente (no toca el estado editable del diseño), cualquier
// etiqueta con variant_id: la reconstruye con la definición ACTUAL de su variante — mismo criterio que
// LabelShapeVariantResolver (backend), para que el canvas muestre lo mismo que termina impreso.
export const resolvePageLabelVariants = (
	elements: PdfDesignElement[],
	layoutGroups: PdfLayoutGroup[],
	labelShapes: LabelShape[]
): { elements: PdfDesignElement[]; layoutGroups: PdfLayoutGroup[] } => {
	let result = elements;
	let groups = layoutGroups;

	elements
		.filter(el => el.type === 'background' && el.label_shape_id && el.variant_id)
		.forEach(label => {
			const shape = labelShapes.find(s => s.id === label.label_shape_id);
			const variant = shape?.data?.variants?.find(v => v.id === label.variant_id);
			if (!shape || !variant) return;

			const onTop = elementsOnLabel(label, result);
			const onTopIds = new Set(onTop.map(el => el.id));
			const savedIds = new Set([label.id, ...onTopIds]);
			const removeGroupIds = new Set(layoutGroupsWithin(savedIds, groups).map(g => g.id));

			let placed = instantiateLabelVariant(variant, shape, label.x_cm, label.y_cm, label.id);
			if (label.rotation_deg) placed = rotatePlacedLabelVariant(placed, label.rotation_deg);

			const newLabel = { ...placed.label, group_id: label.group_id ?? null };
			const next: PdfDesignElement[] = [];
			result.forEach(el => {
				if (el.id === label.id) {
					next.push(newLabel, ...placed.elements);
					return;
				}
				if (onTopIds.has(el.id)) return;
				next.push(el);
			});
			result = next;
			groups = [...groups.filter(g => !removeGroupIds.has(g.id)), ...placed.layoutGroups];
		});

	return { elements: result, layoutGroups: groups };
};
```

(Se reemplaza, no se inserta al final, para no alterar el orden de pintado de lo que ya estaba.)

**b. `components/productPdfDesigns/editor/CanvasArea.vue`** — `renderElements` (línea ~316) es el
lugar correcto: ya es "lo que se dibuja", separado de `elements` (línea ~301, el estado editable real
que no hay que tocar). El componente ya recibe `props.labelShapes` completo (se usa en
`deriveCutLines`/`labelShapeFor`), así que no hace falta inyectar nada nuevo.

```ts
// ANTES
const renderElements = computed(() => {
	if (previewMode.value) {
		return applyLayoutGroups(
			editor.activePage.value.layout_groups,
			PdfDesignHelper.applyIconLengthScale(
				elements.value.map(el => resolvePreviewElement(el, previewContext.value))
			),
			{ fontFamilyFor: catalogs.fontFamilyFor }
		);
	}
	return pageRenderElements(
		{
			layout_groups: editor.activePage.value.layout_groups,
			elements: elements.value.map(placeholderDynamicColors)
		},
		{ fontFamilyFor: catalogs.fontFamilyFor }
	);
});
```

```ts
// DESPUÉS
const resolvedPage = computed(() =>
	PdfLabelShapeVariantsHelper.resolvePageLabelVariants(
		elements.value,
		editor.activePage.value.layout_groups ?? [],
		props.labelShapes
	)
);
const renderElements = computed(() => {
	if (previewMode.value) {
		return applyLayoutGroups(
			resolvedPage.value.layoutGroups,
			PdfDesignHelper.applyIconLengthScale(
				resolvedPage.value.elements.map(el => resolvePreviewElement(el, previewContext.value))
			),
			{ fontFamilyFor: catalogs.fontFamilyFor }
		);
	}
	return pageRenderElements(
		{
			layout_groups: resolvedPage.value.layoutGroups,
			elements: resolvedPage.value.elements.map(placeholderDynamicColors)
		},
		{ fontFamilyFor: catalogs.fontFamilyFor }
	);
});
```

(Nombren el import del helper como ya lo tengan importado en el archivo — puede que ya exista como
`LabelShapeVariantsHelper` a secas; usé un alias arriba solo para distinguirlo en el ejemplo.)

**No hace falta tocar** la miniatura de la galería (`components/productPdfDesigns/gallery/
PdfDesignPageThumb.vue`): a diferencia de las temáticas, esa viene de `GET /product-pdf-designs`
(`index()`), que el backend YA resuelve en vivo del lado del servidor — llega con las etiquetas de
variante ya reconstruidas, sin que el front tenga que hacer nada.

## 3. Un elemento "de arriba" resuelto en vivo no es editable como los demás — decisión de UX pendiente

Los elementos que `resolvePageLabelVariants` genera para dibujar (los que reemplazan lo que había
sobre la etiqueta) tienen ids NUEVOS en cada render — no existen con ese id en `activePage.value.
elements` (el estado real que se guarda). Si el admin hace click/arrastra uno de esos glifos en el
canvas, lo que intente mover no corresponde a nada persistido: el `mousedown`/`startInteraction` actual
asume que todo elemento dibujado tiene una contraparte real para mutar.

Como la decisión de producto fue "la variante siempre gana" (cualquier retoque puntual se pierde en la
próxima resolución), no tiene mucho sentido dejar que se sigan editando uno por uno de todas formas —
quedaría una edición que se ve bien hasta la próxima vez que se resuelva (reabrir el diseño, regenerar
el PDF) y ahí desaparece sin aviso. Dejamos la solución concreta para ustedes porque es una decisión de
UX, no algo que el backend pueda definir; algunas opciones:

- Bloquear selección/arrastre de un elemento cuyo id no está en `elements.value` (viene de una
  resolución), mostrando algo como "Este contenido viene de la variante 'X' — para cambiarlo, editá la
  variante" con un atajo a esa variante.
- Dejar editable el LABEL en sí (conserva su id real, sí se puede mover/rotar/agrupar) pero no sus
  elementos de arriba.
- Alguna otra señal visual (candado, opacidad) que dejen en claro que ese contenido no es propio de
  este diseño.

Lo único que no sería seguro es dejar que el click "silenciosamente no haga nada" sin explicar por qué,
o que tire un error si `startInteraction` no encuentra el id en el estado real.
