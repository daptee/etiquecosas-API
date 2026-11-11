# Cambios de front-end pendientes de portar: tamaño de celda vía `label_shape_id`

Esto ya está aplicado en `storage/app/etiquecosas-admin-development` (el admin que corre en este repo) — si el front-end real vive en otro repositorio, hay que portar estos cambios ahí a mano.

Contexto: antes, en modo "Acumulando", el tamaño real de la etiqueta salía de `sheet.width_cm/height_cm` — obligaba a achicar la página 1 al tamaño de la etiqueta (ej. 6×1cm) en vez de diagramar sobre una hoja completa como siempre. Ahora el backend (`AccumulatingPdfDesignService::resolverTamanoDeCelda()`) resuelve el tamaño real desde la **forma de etiqueta** (`label_shape_id`) del elemento `background` — `sheet` vuelve a ser solo el lienzo de edición, puede quedar en A4 completo. El front tiene que calcular el mismo tamaño de la misma forma para que la vista previa (tamaño mostrado, grilla automática, validación de "la celda es más grande que la hoja") coincida con lo que el backend realmente va a generar.

---

## 1. `utils/PdfAccumulationHelper.ts`

### 1.a Nuevo helper `resolveAccumulationCellSize`

Agregar el import de `LabelShape` y la función nueva (misma prioridad que el backend):

```ts
import type { LabelShape } from '~/types/labelShapes/ILabelShape';

// Real size of ONE label — same priority as the backend
// (AccumulatingPdfDesignService::resolverTamanoDeCelda): `sheet.width_cm/height_cm` is just the editing
// canvas (it can stay full A4, like normal mode) — the real physical size comes from the label SHAPE on
// the cell's `background` element, or the design's own shape, or the background's own size, and only
// falls back to `sheet` for a design with no background at all (100% hand-drawn, no shape picker used).
export const resolveAccumulationCellSize = (
	page: PdfDesignPage | undefined,
	designLabelShapeId: number | null | undefined,
	labelShapes: LabelShape[]
): { width_cm: number; height_cm: number } => {
	const fallback = page?.sheet ?? { width_cm: 4.5, height_cm: 3.5 };
	if (!page) return fallback;

	const background = page.elements.find(el => el.type === 'background');
	const shapeById = (id: number | null | undefined) =>
		id ? labelShapes.find(s => s.id === id) : undefined;

	const backgroundShape = shapeById(background?.label_shape_id);
	if (backgroundShape) {
		return { width_cm: backgroundShape.widthCm, height_cm: backgroundShape.heightCm };
	}

	const designShape = shapeById(designLabelShapeId);
	if (designShape) {
		return { width_cm: designShape.widthCm, height_cm: designShape.heightCm };
	}

	if (background) {
		return { width_cm: background.width_cm, height_cm: background.height_cm };
	}

	return fallback;
};
```

Colocarla antes de `autoAccumulationGrid` (que sigue igual, solo cambia lo que se le pasa como `cell`).

### 1.b Quitar el warning de `layout_groups` en `accumulationIssues`

Ya no es cierto — `layout_groups` ahora SÍ se aplica en modo acumulable (ver `EtiquetaService::resolverCeldaAcumulada()` en el backend). Quitar este bloque:

```ts
// QUITAR — esto ya no es verdad
if (page.layout_groups?.length) {
	issues.push(
		'Los grupos con anclaje (ícono + texto) no se aplican: cada elemento queda donde está.'
	);
}
```

El resto de `accumulationIssues` (campos dinámicos no soportados, colores/tipografías "De la compra" atados a un atributo de catálogo) sigue siendo válido tal cual, no se toca.

---

## 2. `components/productPdfDesigns/editor/AccumulationConfig.vue`

### 2.a Inyectar el catálogo de formas de etiqueta

Mismo patrón que ya usan `BorrowedPageDialog.vue`/`PdfDesignPageThumb.vue` (la página de edición ya lo provee con `provide('labelShapesList', labelShapesList)`):

```ts
import type { LabelShape } from '~/types/labelShapes/ILabelShape';
import {
	ACCUMULATION_DEFAULTS,
	ACCUMULATION_LIMITS,
	ACCUMULATION_SHEET,
	accumulationIssues,
	autoAccumulationGrid,
	resolveAccumulationCellSize   // NUEVO
} from '~/utils/PdfAccumulationHelper';

// Catálogo de formas de etiqueta, provisto por la página — ver resolveAccumulationCellSize: el tamaño
// real de la celda sale de la forma del `background`, no de `sheet` (que es solo el lienzo de edición).
const labelShapesList = inject<Ref<LabelShape[]>>('labelShapesList', ref([]));  // NUEVO
```

### 2.b Reemplazar `cellSize`/`autoGrid` para que usen el nuevo resolver

```ts
// ANTES
const cellSize = computed(() =>
	cell.value
		? `${formatCm(cell.value.sheet.width_cm)} × ${formatCm(cell.value.sheet.height_cm)} cm`
		: '—'
);
...
const autoGrid = computed(() =>
	cell.value ? autoAccumulationGrid(cell.value.sheet, gapCm.value) : { columns: 0, rows: 0 }
);
```

```ts
// DESPUÉS
const cellDimensions = computed(() =>
	resolveAccumulationCellSize(
		cell.value,
		editor.design.value.labelShapeId ?? null,
		labelShapesList.value
	)
);

const formatCm = (cm: number) => cm.toLocaleString('es-AR', { maximumFractionDigits: 2 });
const cellSize = computed(() =>
	cell.value
		? `${formatCm(cellDimensions.value.width_cm)} × ${formatCm(cellDimensions.value.height_cm)} cm`
		: '—'
);
...
const autoGrid = computed(() =>
	cell.value ? autoAccumulationGrid(cellDimensions.value, gapCm.value) : { columns: 0, rows: 0 }
);
```

### 2.c Texto del panel (opcional, solo copy)

El texto de ayuda de la sección "Celda" decía "achicá la página 1" — ya no aplica. Cambiado a:

```html
<p class="ac-text">
	La página 1 define <strong>una sola etiqueta</strong>: su tamaño real
	<span class="ac-mono">{{ cellSize }}</span> sale de la forma de etiqueta del
	fondo (no hace falta achicar la página — podés seguir diseñando en una hoja
	completa, como siempre). Se repite una vez por pedido del día en hojas de
	<span class="ac-mono">{{ sheetSize }}</span>.
</p>
...
<p v-if="!cellFits" class="ac-note ac-note--error">
	<v-icon icon="mdi-alert-circle-outline" size="14" />
	La etiqueta es más grande que la hoja: asigná una forma de etiqueta más chica
	al fondo.
</p>
```

---

## Importante

Si el diseño NO tiene ningún elemento `background` (celda armada 100% a mano, sin pasar por el picker de formas), el tamaño sigue saliendo de `sheet` como antes — no rompe nada para los diseños ya armados así.
