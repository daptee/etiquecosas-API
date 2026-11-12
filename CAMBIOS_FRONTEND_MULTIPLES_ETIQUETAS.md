# Cambios de front-end pendientes: varias etiquetas en un mismo diseño acumulable

Esto **no está aplicado** en `storage/app/etiquecosas-admin-development` — es la propuesta de cambio para portar al repo real del front. Revisado contra la versión actual de ese repo (ya tiene aplicados los cambios de `CAMBIOS_FRONTEND_CANTIDAD_ACUMULABLE.md` y `CAMBIOS_FRONTEND_TAMANO_CELDA_LABEL_SHAPE.md` de antes).

## Contexto

Backend ya implementado (ver `PDF_DISENOS_ACUMULABLES.md`, sección 2.1): la página 1 de un diseño acumulable puede tener **más de un elemento `background`** — cada uno es una etiqueta distinta (su propio `label_shape_id`, su propio texto/ícono anclado vía `layout_groups` o puesto a mano encima). Al vincular un producto al diseño, el vínculo (`product_pdf_design_products`) ahora acepta `labelElementId`: el `id` de ESE `background` puntual. Sin él (o `null`), usa la primera etiqueta de la página — compat total con los diseños de una sola etiqueta de siempre.

Requiere que el backend ya tenga aplicada `database/sql/alter_product_pdf_design_products_add_label_element.sql` (agrega la columna) — confirmar antes de probar esto en un entorno.

**Decisión de alcance para esta primera versión**: la elección de etiqueta es **por producto**, no por variante — un producto vinculado al diseño usa la misma etiqueta para todas las variantes/temáticas que tenga seleccionadas. Si en algún caso real un mismo producto necesita etiquetas distintas según la variante, hay que extender esto (ver nota al final de la sección 6) — no debería hacer falta para el caso que lo motivó (producto 92932, sin variantes).

---

## 1. Tipos (`types/productPdfDesigns/IProductPdfDesign.ts`)

### 1.a `ProductPdfDesignVariantLink` — leer el campo

```ts
export interface ProductPdfDesignVariantLink {
	linkId: number;
	productId: number;
	themeKey: number | null;
	pageId: string | null;
	// NUEVO — PDF_DISENOS_ACUMULABLES.md sección 2.1: id de un elemento `background` de ESTE
	// diseño (diseño acumulable con varias etiquetas en la misma página) que le corresponde a
	// este producto. null = la primera etiqueta de la página.
	labelElementId: string | null;
	sortOrder: number;
}
```

### 1.b `BulkManyLinkEntry` — mandarlo al guardar

```ts
export interface BulkManyLinkEntry {
	productId: number;
	themeKeys: (number | null)[];
	pageId?: string | null;
	// NUEVO
	labelElementId?: string | null;
	sortOrder?: number;
}
```

### 1.c `PdfPendingProduct` — stage local en el editor

```ts
export interface PdfPendingProduct {
	id: number;
	name: string;
	sku?: string;
	themeKeys: (number | null)[];
	// NUEVO — solo tiene sentido cuando el diseño es acumulable y su página 1 tiene 2+ `background`;
	// se ignora en cualquier otro caso. Aplica a TODAS las themeKeys de este producto (ver "Decisión
	// de alcance" arriba).
	labelElementId?: string | null;
}
```

---

## 2. Helper nuevo (`utils/PdfAccumulationHelper.ts`)

Agregar una función que enumere los `background` de la página con un nombre legible (tamaño real, resuelto con la misma prioridad que ya usa `resolveAccumulationCellSize`):

```ts
export interface AccumulationLabelOption {
	elementId: string;
	label: string; // ej. "Etiqueta 1 — 5 × 1 cm"
	width_cm: number;
	height_cm: number;
}

// Cada elemento `background` de la página es una etiqueta distinta (PDF_DISENOS_ACUMULABLES.md
// sección 2.1) — esto es lo que se ofrece al vincular un producto, para elegir cuál le toca.
// Con un solo background (el caso de siempre) devuelve un array de 1 solo elemento: no hace falta
// mostrar ningún selector en ese caso (ver uso en ProductVariantsPicker.vue).
export const pageLabelOptions = (
	page: PdfDesignPage | undefined,
	designLabelShapeId: number | null | undefined,
	labelShapes: LabelShape[]
): AccumulationLabelOption[] => {
	if (!page) return [];
	const shapeById = (id: number | null | undefined) =>
		id ? labelShapes.find(s => s.id === id) : undefined;

	return page.elements
		.filter(el => el.type === 'background')
		.map((bg, index) => {
			const shape = shapeById(bg.label_shape_id) ?? shapeById(designLabelShapeId);
			const width_cm = shape?.widthCm ?? bg.width_cm;
			const height_cm = shape?.heightCm ?? bg.height_cm;
			return {
				elementId: bg.id,
				label: `Etiqueta ${index + 1} — ${formatCm(width_cm)} × ${formatCm(height_cm)} cm`,
				width_cm,
				height_cm
			};
		});
};

const formatCm = (cm: number) => cm.toLocaleString('es-AR', { maximumFractionDigits: 2 });
```

(`formatCm` ya existe duplicada en `AccumulationConfig.vue` — al portar esto conviene centralizarla acá y que ese componente la importe, en vez de tener dos copias.)

### Nota aparte, no relacionada a esto: `ACCUMULATION_SHEET` desactualizado

`ACCUMULATION_SHEET` en este archivo sigue en `{ width_cm: 18.5, height_cm: 29 }` — el backend usa A4 real (`21 × 29.7`) desde este mismo ciclo de cambios (`AccumulatingPdfDesignService::SHEET_WIDTH_CM/HEIGHT_CM`). Esto solo afecta la ESTIMACIÓN de grilla automática que se muestra en el editor (`autoAccumulationGrid`) — el PDF real ya sale bien, pero el cálculo mostrado en pantalla (columnas/filas "Auto") puede no coincidir. Conviene corregirlo de una:

```ts
export const ACCUMULATION_SHEET = { width_cm: 21, height_cm: 29.7 };
```

---

## 3. `components/productPdfDesigns/editor/AccumulationConfig.vue`

### 3.a Copy de la sección "Celda"

Hoy dice "La página 1 define **una sola etiqueta**" — ya no es necesariamente así. Cambiar por algo que cubra los dos casos:

```html
<p class="ac-text">
	La página 1 define {{ labelOptions.length > 1 ? 'varias etiquetas' : 'una sola etiqueta' }}: cada
	elemento de fondo con forma asignada es una etiqueta distinta (no hace falta achicar la página —
	podés seguir diseñando en una hoja completa, como siempre). Se repiten una vez por pedido del día
	en hojas de <span class="ac-mono">{{ sheetSize }}</span>.
</p>
<ul v-if="labelOptions.length > 1" class="ac-label-list">
	<li v-for="opt in labelOptions" :key="opt.elementId">{{ opt.label }}</li>
</ul>
<p v-if="labelOptions.length > 1" class="ac-hint">
	Con más de una etiqueta, elegí cuál le corresponde a cada producto desde "Productos y variantes"
	más abajo.
</p>
```

```ts
import { pageLabelOptions /* + lo que ya importaba */ } from '~/utils/PdfAccumulationHelper';

const labelOptions = computed(() =>
	pageLabelOptions(cell.value, editor.design.value.labelShapeId ?? null, labelShapesList.value)
);
```

### 3.b `cellSize`/`cellFits`/`issues` con varias etiquetas

Hoy estos tres computeds (`cellDimensions`/`cellFits`/`issues` vía `accumulationIssues`) toman el **primer** `background` de la página nada más (`page.elements.find(el => el.type === 'background')`), lo mismo pasa en `resolveAccumulationCellSize`. Con 2+ etiquetas eso deja de alcanzar — en particular `cellFits` ("la etiqueta es más grande que la hoja") debería chequear TODAS, no solo la primera.

Más simple que generalizar esos helpers: cuando `labelOptions.length > 1`, mostrar el tamaño de cada una en la lista de arriba (ya tienen `width_cm`/`height_cm`) en vez de un único `cellSize`, y chequear "cabe en la hoja" por cada una:

```ts
const sheetSize = `${formatCm(ACCUMULATION_SHEET.width_cm)} × ${formatCm(ACCUMULATION_SHEET.height_cm)} cm`;

const labelFits = (opt: AccumulationLabelOption) => {
	const grid = autoAccumulationGrid(opt, gapCm.value);
	return grid.columns > 0 && grid.rows > 0;
};
```

y en el template, al lado de cada `<li>` de `labelOptions`, un ícono de alerta si `!labelFits(opt)` (mismo `ac-note--error` que ya existe). Con una sola etiqueta (`labelOptions.length === 1`) se puede seguir usando `cellSize`/`cellFits` tal cual están, sin tocarlos.

---

## 4. `components/productPdfDesigns/editor/ProductVariantsPicker.vue`

Agregar un prop con las opciones de etiqueta (se lo pasa `RightSidebar.vue`, ver sección 5) y, por cada producto ya agregado, un selector — solo visible cuando hay 2+ etiquetas:

```html
<li v-for="product in products" :key="product.id" class="ptp-product">
	<div class="ptp-product-info">
		<span class="ptp-product-name" :title="product.name">{{ product.name }}</span>
		<span class="ptp-product-meta">
			<span v-if="product.sku">SKU {{ product.sku }} · </span>
			<span :class="{ 'ptp-warn': isMissingVariants(product) }">{{ summary(product) }}</span>
		</span>
	</div>
	<v-select
		v-if="labelOptions.length > 1"
		:model-value="product.labelElementId ?? labelOptions[0].elementId"
		:items="labelOptions"
		item-title="label"
		item-value="elementId"
		density="compact"
		variant="outlined"
		hide-details
		style="max-width: 180px"
		@update:model-value="
			(elementId: string) => emit('set-label-element', { productId: product.id, elementId })
		"
	/>
	<v-btn v-if="hasVariants(product.id)" ... >Variantes</v-btn>
	<v-btn icon="mdi-close" ... />
</li>
```

```ts
const props = defineProps({
	products: { type: Array as PropType<PdfPendingProduct[]>, default: () => [] },
	variantsById: { type: Object as PropType<Record<number, ProductVariant[]>>, default: () => ({}) },
	typeById: { type: Object as PropType<Record<number, number | null>>, default: () => ({}) },
	// NUEVO
	labelOptions: { type: Array as PropType<AccumulationLabelOption[]>, default: () => [] }
});

const emit = defineEmits<{
	(e: 'add-product', product: PdfPendingProduct): void;
	(e: 'remove-product', productId: number): void;
	(e: 'set-variants', payload: { productId: number; themeKeys: (number | null)[] }): void;
	// NUEVO
	(e: 'set-label-element', payload: { productId: number; elementId: string }): void;
}>();
```

(El layout de `.ptp-product` es un `flex` con `gap`, así que el `v-select` nuevo entra entre `.ptp-product-info` y los botones sin romper nada — puede necesitar un `flex: none` como ya tienen los botones.)

---

## 5. `components/productPdfDesigns/editor/RightSidebar.vue`

Pasar las opciones calculadas y manejar el evento nuevo, mismo patrón que `handleSetVariants`:

```html
<ProductVariantsPicker
	:products="editor.pendingProducts.value"
	:variants-by-id="editor.productVariantsById.value"
	:type-by-id="editor.productTypeById.value"
	:label-options="labelOptions"
	@add-product="handleAddProduct"
	@remove-product="handleRemoveProduct"
	@set-variants="handleSetVariants"
	@set-label-element="handleSetLabelElement"
/>
```

```ts
import { pageLabelOptions } from '~/utils/PdfAccumulationHelper';

// Solo importa cuando el diseño es acumulable — en modo 'single' siempre vacío, así que
// ProductVariantsPicker nunca muestra el selector ahí.
const labelOptions = computed(() =>
	editor.design.value.fillMode === 'accumulating'
		? pageLabelOptions(editor.pages.value[0], editor.design.value.labelShapeId ?? null, props.labelShapes)
		: []
);

const handleSetLabelElement = (payload: { productId: number; elementId: string }) => {
	editor.pendingProducts.value = editor.pendingProducts.value.map(p =>
		p.id === payload.productId ? { ...p, labelElementId: payload.elementId } : p
	);
};
```

---

## 6. `pages/productPdfDesigns/edit/[id].vue` — guardar y releer el vínculo

### 6.a Leer el valor ya guardado al abrir el diseño

`pendingProductsFromDesign()` arma `themeKeys` agrupando los links por producto — hay que llevarse también el `labelElementId` de esos links (asumiendo que, por la decisión de alcance, todas las themeKeys de un mismo producto comparten el mismo valor — tomar el del primero):

```ts
const fromLinks: PdfPendingProduct[] = design.products
	.map(product => {
		const linksOfProduct = design.variantLinks.filter(link => link.productId === product.id);
		return {
			id: product.id,
			name: product.name,
			sku: product.sku,
			themeKeys: [...new Set(linksOfProduct.map(link => link.themeKey))].filter(themeKey =>
				isOwn(product.id, themeKey)
			),
			// NUEVO
			labelElementId: linksOfProduct[0]?.labelElementId ?? null
		};
	})
	.filter(product => product.themeKeys.length > 0);
```

### 6.b `LinkCombo`/`currentCombos`/`linkKey` — que el diff detecte un cambio de etiqueta

```ts
type LinkCombo = { productId: number; themeKey: number | null; labelElementId: string | null };

const currentCombos = (): LinkCombo[] =>
	editor.pendingProducts.value.flatMap(product =>
		product.themeKeys.map(themeKey => ({
			productId: product.id,
			themeKey,
			labelElementId: product.labelElementId ?? null
		}))
	);
```

`inCombos`/`allCombos` siguen comparando por `productId`+`themeKey` nada más (no hace falta que también miren `labelElementId` ahí — esa comparación es sobre "¿sigue vinculado este producto+variante?", no sobre el valor de la etiqueta).

`LinkKey` y `linkKey()` sí necesitan el campo nuevo, para que cambiar SOLO la etiqueta de un producto ya vinculado se detecte como "hay que desvincular y volver a vincular" (no hay endpoint de "actualizar" un vínculo existente, mismo mecanismo que ya usan `pageId`/`sortOrder`):

```ts
type LinkKey = LinkCombo & { pageId: string | null; sortOrder: number };
const linkKey = (l: LinkKey) =>
	`${l.productId}|${l.themeKey}|${l.labelElementId}|${l.pageId}|${l.sortOrder}`;
```

Y en `desiredOwn` (línea ~718), pasar el campo al armar cada `LinkKey`:

```ts
const desiredOwn: LinkKey[] = combos.flatMap<LinkKey>(combo =>
	perPage
		? pages
				.map((page, position) => ({ page, position }))
				.filter(x => !x.page.source)
				.map(x => ({ ...combo, pageId: x.page.id, sortOrder: x.position }))
		: [{ ...combo, pageId: null, sortOrder: 0 }]
);
```

(`combo` ya trae `labelElementId` porque `currentCombos()` lo incluye ahora — no hace falta tocar nada más acá, el spread `...combo` ya lo arrastra.)

### 6.c `linkAll()` — mandarlo en el payload

Hoy agrupa por `productId|pageId|sortOrder` — agregar `labelElementId` a esa clave de agrupación y al payload final:

```ts
const linkAll = async (targetId: number, adds: LinkKey[]) => {
	if (!adds.length) return;
	const grouped = new Map<
		string,
		{
			productId: number;
			pageId: string | null;
			labelElementId: string | null;
			sortOrder: number;
			themeKeys: (number | null)[];
		}
	>();
	adds.forEach(a => {
		const k = `${a.productId}|${a.pageId}|${a.labelElementId}|${a.sortOrder}`;
		const entry = grouped.get(k) ?? {
			productId: a.productId,
			pageId: a.pageId,
			labelElementId: a.labelElementId,
			sortOrder: a.sortOrder,
			themeKeys: []
		};
		entry.themeKeys.push(a.themeKey);
		grouped.set(k, entry);
	});
	const result = await bulkLinkMany(targetId, {
		links: [...grouped.values()].map(g => ({
			productId: g.productId,
			themeKeys: g.themeKeys,
			...(g.pageId != null ? { pageId: g.pageId, sortOrder: g.sortOrder } : {}),
			...(g.labelElementId != null ? { labelElementId: g.labelElementId } : {})
		}))
	});
	if (result) skipped.push(...result.skipped);
};
```

---

## 7. `services/productPdfDesigns/productPdfDesigns.service.ts` — mapear la respuesta del backend

`mapProductPdfDesign()` ya mapea `pageId` de `variantLinks` (línea ~85) — agregar `labelElementId` al lado, mismo patrón snake/camel:

```ts
variantLinks: (raw.variantLinks ?? rows).map((link: any) => ({
	linkId: link.pivot?.id ?? link.linkId,
	productId: productIdOf(link),
	themeKey: link.pivot?.theme_key ?? link.pivot?.themeKey ?? link.themeKey ?? null,
	pageId: link.pivot?.page_id ?? link.pivot?.pageId ?? link.pageId ?? null,
	// NUEVO
	labelElementId:
		link.pivot?.label_element_id ?? link.pivot?.labelElementId ?? link.labelElementId ?? null,
	sortOrder: Number(link.pivot?.sort_order ?? link.pivot?.sortOrder ?? link.sortOrder ?? 0)
})),
```

---

## Si más adelante hace falta por variante, no por producto

Si en algún momento un mismo producto necesita etiquetas distintas según la variante comprada (no es el caso de 92932, que no tiene variantes), `PdfPendingProduct.labelElementId` tendría que pasar de un único valor a un mapa por `themeKey` (ej. `labelElementIdByThemeKey: Record<string, string | null>`), y el selector de la sección 4 se movería de la fila del producto a cada fila de variante dentro de `ProductVariantsDialog.vue`. Las secciones 6/7 de acá (el diff de `syncLinks` y el mapeo de `variantLinks`) ya están armadas por-link (no por-producto), así que no necesitan cambios para ese caso — el único impacto sería en el stage local (`PdfPendingProduct`) y en de dónde sale `labelElementId` al armar `currentCombos()`.
