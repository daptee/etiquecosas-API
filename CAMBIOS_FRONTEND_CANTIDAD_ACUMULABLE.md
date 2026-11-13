# Cambios de front-end pendientes de portar: cantidad por pedido en diseños acumulables ("1 etiqueta por compra" vs "x1/x2/x3")

Esto **no está aplicado** en `storage/app/etiquecosas-admin-development` (ni en ningún otro lado del front) — es sólo la propuesta de cambio para portar al repo real del front, si se decide habilitarlo.

## Contexto

El backend (`ProductPdfDesign::duplicaCeldaPorCantidad()` + `AccumulatingPdfDesignService`) ya soporta **dos modos** para un pedido con cantidad > 1 dentro de un diseño acumulable, vía la columna `accumulation_quantity_mode`:

- `"duplicate"` (default): repite la celda esa cantidad de veces como etiquetas físicas separadas — si alguien compró 3, salen 3 celdas idénticas.
- `"badge"`: deja **una sola celda** y le suma un cartelito `"xN"` al lado (mismo mecanismo que ya usan hoy Cinta Coser/Cinta Planchar).

El endpoint de update del diseño (`POST /product-pdf-designs/{id}`) ya acepta `accumulationQuantityMode: "duplicate" | "badge"` — está wireado en el backend de punta a punta. Lo único que falta es exponerlo como opción en el editor del front.

**Mientras tanto, se puede configurar directamente por SQL** (sin tocar el front):

```sql
-- "duplicate": 1 etiqueta física por unidad comprada (comportamiento actual/default)
UPDATE product_pdf_designs SET accumulation_quantity_mode = 'duplicate' WHERE id = {id_diseño};

-- "badge": 1 sola celda por pedido + cartelito "xN"
UPDATE product_pdf_designs SET accumulation_quantity_mode = 'badge' WHERE id = {id_diseño};
```

---

## 1. `types/productPdfDesigns/IProductPdfDesign.ts`

Agregar el campo a `PdfAccumulationSettings`:

```ts
export type PdfAccumulationQuantityMode = 'duplicate' | 'badge';

export interface PdfAccumulationSettings {
	fillMode: PdfFillMode;
	accumulationGroupAttribute: string | null;
	accumulationCols: number | null;
	accumulationRows: number | null;
	accumulationGapCm: number;
	accumulationQuantityMode: PdfAccumulationQuantityMode; // NUEVO
}
```

## 2. `utils/PdfAccumulationHelper.ts`

Agregar el default (entra solo en `ACCUMULATION_KEYS`, que se deriva de `Object.keys(ACCUMULATION_DEFAULTS)`) y en `accumulationPayload()`:

```ts
export const ACCUMULATION_DEFAULTS: PdfAccumulationSettings = {
	fillMode: 'single',
	accumulationGroupAttribute: null,
	accumulationCols: null,
	accumulationRows: null,
	accumulationGapCm: 0.3,
	accumulationQuantityMode: 'duplicate' // NUEVO
};
```

```ts
export const accumulationPayload = (
	design: Partial<PdfAccumulationSettings>
): PdfAccumulationSettings => ({
	fillMode: design.fillMode ?? ACCUMULATION_DEFAULTS.fillMode,
	accumulationGroupAttribute: design.accumulationGroupAttribute?.trim() || null,
	accumulationCols: design.accumulationCols ?? null,
	accumulationRows: design.accumulationRows ?? null,
	accumulationGapCm: design.accumulationGapCm ?? ACCUMULATION_DEFAULTS.accumulationGapCm,
	accumulationQuantityMode: design.accumulationQuantityMode ?? ACCUMULATION_DEFAULTS.accumulationQuantityMode // NUEVO
});
```

### Importante — revisar el mapeo de lectura

`services/productPdfDesigns/productPdfDesigns.service.ts` → `mapProductPdfDesign()` arma el objeto `ProductPdfDesign` a mano (no hace `...raw`) y **actualmente no incluye `fillMode`/`accumulationGroupAttribute`/`accumulationCols`/`accumulationRows`/`accumulationGapCm`/`pagesOutputMode` en absoluto** — ninguno de estos campos se lee del backend al abrir un diseño existente en el editor, solo quedan en los defaults de `normalizeAccumulation()`. Esto es anterior a este cambio y no es algo que haya tocado ahora, pero **si se agrega `accumulationQuantityMode` sin corregir esto, va a tener el mismo problema**: se podrá elegir en el editor, pero al reabrir un diseño ya guardado con `"badge"` el campo va a volver a mostrar `"duplicate"` porque nunca se lee del backend.

Si se decide arreglar esto de punta a punta, agregar a `mapProductPdfDesign()`:

```ts
fillMode: raw.fill_mode ?? raw.fillMode ?? 'single',
accumulationGroupAttribute: raw.accumulation_group_attribute ?? raw.accumulationGroupAttribute ?? null,
accumulationCols: raw.accumulation_cols ?? raw.accumulationCols ?? null,
accumulationRows: raw.accumulation_rows ?? raw.accumulationRows ?? null,
accumulationGapCm: raw.accumulation_gap_cm ?? raw.accumulationGapCm ?? 0.3,
accumulationQuantityMode: raw.accumulation_quantity_mode ?? raw.accumulationQuantityMode ?? 'duplicate',
pagesOutputMode: raw.pages_output_mode ?? raw.pagesOutputMode ?? 'separate',
```

(Si el front real ya tiene esto resuelto de otra forma — por ejemplo un transform global snake→camel en `useApi`/`adminAPI` que este repo mirror no tiene — este punto puede no aplicar; confirmar contra el repo real antes de portarlo.)

---

## 3. `components/productPdfDesigns/editor/AccumulationConfig.vue`

Nueva sección, por ejemplo entre "Agrupar por atributo" y "Celdas por hoja":

```html
<div class="ac-sect">
	<div class="ac-sect-title">Cantidad por pedido</div>
	<div class="ac-modes" role="radiogroup" aria-label="Cantidad por pedido">
		<button
			v-for="mode in QUANTITY_MODES"
			:key="mode.value"
			type="button"
			role="radio"
			class="ac-mode"
			:class="{ on: quantityMode === mode.value }"
			:aria-checked="quantityMode === mode.value"
			@click="editor.onUpdateAccumulation({ accumulationQuantityMode: mode.value })"
		>
			<v-icon :icon="mode.icon" size="18" class="ac-mode-icon" />
			<span class="ac-mode-title">{{ mode.title }}</span>
			<span class="ac-mode-text">{{ mode.text }}</span>
		</button>
	</div>
	<p class="ac-hint">
		Un pedido de "Juan x3" sale como 3 etiquetas iguales (una al lado de la otra) en modo
		"Una etiqueta por unidad", o como una sola etiqueta "Juan x3" en modo "Un cartelito con la cantidad".
	</p>
</div>
```

```ts
import type { PdfAccumulationQuantityMode } from '~/types/productPdfDesigns/IProductPdfDesign';

const QUANTITY_MODES: { value: PdfAccumulationQuantityMode; icon: string; title: string; text: string }[] = [
	{
		value: 'duplicate',
		icon: 'mdi-content-duplicate',
		title: 'Una etiqueta por unidad',
		text: 'Cantidad 3 = 3 etiquetas físicas iguales.'
	},
	{
		value: 'badge',
		icon: 'mdi-numeric',
		title: 'Un cartelito con la cantidad',
		text: 'Cantidad 3 = 1 etiqueta + cartelito "x3".'
	}
];

const quantityMode = computed(
	() => editor.design.value.accumulationQuantityMode ?? ACCUMULATION_DEFAULTS.accumulationQuantityMode
);
```

(Reutiliza las mismas clases `.ac-modes`/`.ac-mode`/`.ac-mode-icon`/`.ac-mode-title`/`.ac-mode-text` que ya existen en este archivo para el toggle de "Modo de llenado" — no hace falta CSS nuevo.)
