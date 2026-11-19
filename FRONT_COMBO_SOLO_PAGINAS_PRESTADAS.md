# Front: vínculos de productos en diseños "combo" (páginas de otros PDF)

## Resumen

Un diseño **combo** es el que tiene páginas prestadas de otros diseños (`data.pages[].source`). Ejemplo: **172 "Combo primaria Perritos y Gatitos"**, con páginas de 186 (Objetos), 187 (Lápices) y 188 (Ropa).

Al guardar un combo, el front vincula cada página por separado: las prestadas en su diseño de origen y las propias en el combo. Hasta ahora esos vínculos **no guardaban de qué combo venían**, y eso causaba tres problemas:

1. **Los productos desaparecían del combo después de Guardar.** El combo no tiene vínculos propios, así que el editor no los encontraba al recargar.
2. **"Hojas juntas / separadas" del combo no se respetaba al comprar.** Siempre salía un único PDF.
3. **Combos con las mismas páginas mezclaban sus productos.** Por ejemplo "Combo jardín", que usa 2 de las 3 páginas del de primaria.

El back ya está resuelto: cada vínculo puede guardar **`combo_design_id`**. El front tiene que **mandarlo al vincular** y **leerlo al cargar**.

## Qué cambió en el back

### Nuevo campo en los vínculos

| Campo | Dónde | Significado |
|---|---|---|
| `comboDesignId` (request) / `combo_design_id` (respuesta, en `products[].pivot`) | vínculo producto ↔ diseño | Id del combo que armó ese vínculo por página. `null` = vínculo normal (diseño entero o página suelta). |

Endpoints que **aceptan** `comboDesignId` (opcional; si se manda, `pageId` es obligatorio):

- `POST /product-pdf-designs/{id}/products` → `comboDesignId`
- `POST /product-pdf-designs/{id}/products/bulk` → `comboDesignId`
- `POST /product-pdf-designs/{id}/products/bulk-many` → `links[].comboDesignId`

Endpoints de **borrado masivo**, que ahora respetan el combo:

- `DELETE /product-pdf-designs/{id}/products/bulk` y `DELETE .../bulk-many` (`links[].comboDesignId`):
  - **sin** `comboDesignId`: borran solo vínculos que **no** son de un combo;
  - **con** `comboDesignId`: borran solo los vínculos de ese combo.

  Antes, quitar un producto del diseño "Objetos" entero también borraba las páginas que un combo tenía vinculadas ahí.
- `DELETE /product-pdf-designs/{id}/products/{linkId}` no cambia: borra ese vínculo puntual.

### `GET /product-pdf-designs/{id}` devuelve `combo_links`

Son los vínculos que **este diseño armó como combo**, estén en el diseño que estén:

```json
"combo_links": [
  { "linkId": 369, "designId": 186, "productId": 92940, "product": { "id": 92940, "name": "...", "sku": "..." },
    "themeKey": null, "pageId": "page-1794460887264-4-1170", "sortOrder": 0 },
  { "linkId": 370, "designId": 187, "productId": 92940, "product": { ... },
    "themeKey": null, "pageId": "page-1794460988664-72-9361", "sortOrder": 1 },
  { "linkId": 371, "designId": 188, "productId": 92940, "product": { ... },
    "themeKey": null, "pageId": "page-1794461053819-199-7500", "sortOrder": 2 }
]
```

- `designId` es el diseño donde vive el vínculo: el de origen para una página prestada, o el propio combo para una página propia.
- Un diseño que no es combo devuelve `combo_links: []`.

### Al comprar

Los vínculos de un mismo combo se juntan en un PDF que respeta el **`pagesOutputMode` del combo**:
- `combined`: un archivo con todas las hojas;
- `separate`: un archivo por hoja (`-1`, `-2`, ...).

El archivo lleva el nombre del combo. Si el combo está despublicado, inactivo o borrado, sus páginas no se generan. Los vínculos sin combo funcionan igual que antes.

## Qué hay que cambiar en el front

### 1. Tipos (`types/productPdfDesigns/IProductPdfDesign.ts`)

```ts
export interface ProductPdfDesignVariantLink {
	// ...lo que ya tiene
	// Combo (diseño con páginas de otros) que armó este vínculo por página. null = vínculo normal.
	comboDesignId: number | null;
}

export interface ProductPdfDesignComboLink {
	linkId: number;
	designId: number; // diseño donde vive el vínculo (origen de la página, o el propio combo)
	productId: number;
	product: { id: number; name: string; sku?: string } | null;
	themeKey: number | null;
	pageId: string;
	sortOrder: number;
}

export interface ProductPdfDesign {
	// ...lo que ya tiene
	// Vínculos que este diseño armó como combo (GET /product-pdf-designs/{id} → combo_links).
	comboLinks: ProductPdfDesignComboLink[];
}
```

### 2. Mapeo (`services/productPdfDesigns/productPdfDesigns.service.ts` → `mapProductPdfDesign`)

```ts
variantLinks: (raw.variantLinks ?? rows).map((link: any) => ({
	// ...lo que ya tiene
	comboDesignId: link.pivot?.combo_design_id ?? link.comboDesignId ?? null,
})),
comboLinks: (raw.combo_links ?? raw.comboLinks ?? []).map((l: any) => ({
	linkId: l.linkId,
	designId: l.designId,
	productId: l.productId,
	product: l.product ?? null,
	themeKey: l.themeKey ?? null,
	pageId: l.pageId,
	sortOrder: Number(l.sortOrder ?? 0)
})),
```

### 3. `syncLinks` (`pages/productPdfDesigns/edit/[id].vue`): mandar y filtrar por combo

Cuando el diseño tiene páginas prestadas (`perPage`), **todos** sus vínculos por página llevan `comboDesignId = designId`, tanto los de páginas propias como los de prestadas. Y al comparar contra lo que ya existe, solo se miran los vínculos **de este combo**.

```ts
type LinkKey = LinkCombo & { pageId: string | null; sortOrder: number; comboDesignId: number | null };
const linkKey = (l: LinkKey) =>
	`${l.productId}|${l.themeKey}|${l.labelElementId ?? null}|${l.pageId}|${l.sortOrder}|${l.comboDesignId ?? null}`;
```

**Vínculos propios del diseño:**

```ts
const desiredOwn: LinkKey[] = combos.flatMap<LinkKey>(combo =>
	perPage
		? pages
				.map((page, position) => ({ page, position }))
				.filter(x => !x.page.source)
				.map(x => ({ ...combo, pageId: x.page.id, sortOrder: x.position, comboDesignId: designId }))
		: [{ ...combo, pageId: null, sortOrder: 0, comboDesignId: null }]
);
// Los vínculos de diseño entero (para borrarlos si ahora es combo) + los que ESTE combo creó acá.
// Un vínculo por página que OTRO combo creó en este diseño no se toca.
const existingOwn = currentLinks.filter(
	l =>
		inCombos(l) &&
		((l.pageId == null && l.comboDesignId == null) || l.comboDesignId === designId)
);
```

**Vínculos en cada diseño de origen.** En el loop de `sourceDesignIds`:

```ts
const desired: LinkKey[] = wanted.flatMap(x =>
	combos.map(combo => ({
		...combo,
		labelElementId: null,
		pageId: x.page.source!.pageId,
		sortOrder: x.position,
		comboDesignId: designId
	}))
);
// Solo los vínculos que creó ESTE combo (antes: cualquier vínculo a esas páginas, aunque fuera de otro combo).
const existing = fresh.variantLinks.filter(l => l.comboDesignId === designId && inCombos(l));
```

`relevantPages` ya no hace falta: un vínculo de este combo en una página que se sacó del combo tiene que borrarse igual, y el filtro por `comboDesignId` ya lo incluye.

**`linkAll`:** agrupar y mandar el combo.

```ts
const k = `${a.productId}|${a.pageId}|${a.labelElementId}|${a.sortOrder}|${a.comboDesignId}`;
// ... entry con comboDesignId: a.comboDesignId
const result = await bulkLinkMany(targetId, {
	links: [...grouped.values()].map(g => ({
		productId: g.productId,
		themeKeys: g.themeKeys,
		...(g.pageId != null ? { pageId: g.pageId, sortOrder: g.sortOrder } : {}),
		...(g.labelElementId != null ? { labelElementId: g.labelElementId } : {}),
		...(g.comboDesignId != null ? { comboDesignId: g.comboDesignId } : {})
	}))
});
```

El borrado por `unlinkVariant(designId, linkId)` no cambia. `bulkUnlinkMany` solo se usa para vínculos de diseño entero, que no tienen combo, así que tampoco cambia.

### 4. `pendingProductsFromDesign`: leer los productos del combo desde `comboLinks`

En un combo, un producto + variante es "de este diseño" si tiene un vínculo de este combo (`design.comboLinks`) para **cada** página, en el diseño y la página que corresponden.

```ts
const pendingProductsFromDesign = (
	design: ProductPdfDesign,
	keep: PdfPendingProduct[] = []
): PdfPendingProduct[] => {
	const perPage = design.data.pages.some(page => page.source);
	// Qué vínculo espera cada página del combo: (diseño donde vive, página).
	const expected = design.data.pages.map(page => ({
		designId: page.source?.designId ?? design.id,
		pageId: page.source?.pageId ?? page.id
	}));
	const isOwn = (productId: number, themeKey: number | null) => {
		// Vínculo de diseño entero (no combo, o combo con un vínculo viejo a convertir).
		if (
			design.variantLinks.some(
				l => l.productId === productId && l.themeKey === themeKey && l.pageId == null && l.comboDesignId == null
			)
		)
			return true;
		return (
			perPage &&
			expected.every(e =>
				design.comboLinks.some(
					l =>
						l.productId === productId &&
						l.themeKey === themeKey &&
						l.designId === e.designId &&
						l.pageId === e.pageId
				)
			)
		);
	};

	// Productos candidatos: los vinculados al diseño entero + los de sus vínculos de combo.
	const candidates = new Map(design.products.map(p => [p.id, p]));
	design.comboLinks.forEach(l => {
		if (l.product && !candidates.has(l.productId)) candidates.set(l.productId, l.product);
	});
	const linksOf = (productId: number) => [
		...design.variantLinks.filter(l => l.productId === productId),
		...design.comboLinks.filter(l => l.productId === productId)
	];

	const fromLinks: PdfPendingProduct[] = [...candidates.values()]
		.map(product => {
			const own = design.variantLinks.filter(l => l.productId === product.id);
			return {
				id: product.id,
				name: product.name,
				sku: product.sku,
				themeKeys: [...new Set(linksOf(product.id).map(l => l.themeKey))].filter(themeKey =>
					isOwn(product.id, themeKey)
				),
				labelElementId: own.find(l => l.labelElementId)?.labelElementId ?? null
			};
		})
		.filter(product => product.themeKeys.length > 0);
	const extra = keep.filter(k => !fromLinks.some(p => p.id === k.id)).map(k => ({ ...k, themeKeys: [] }));
	return [...fromLinks, ...extra];
};
```

Después de guardar un diseño existente, `saveDesign` ya vuelve a pedir el diseño con `loadDesign(design.id)`. Hay que copiar también `comboLinks`:

```ts
const relinked = await loadDesign(design.id);
if (relinked) {
	design.variantLinks = relinked.variantLinks;
	design.products = relinked.products;
	design.comboLinks = relinked.comboLinks;
}
```

En la rama de diseño nuevo, `finalDesign` se arma con `linked`, que también tiene que copiar `comboLinks: linked?.comboLinks ?? []`.

### 5. Opción "Hojas juntas / separadas"

En un combo, esa opción ahora sí define cómo sale el PDF al comprar. Si hoy está oculta o deshabilitada para diseños con páginas prestadas, hay que mostrarla. Además, `pagesOutputFields` solo manda `pagesOutputMode` cuando el diseño es o era `combined`. Conviene mandarlo siempre en los combos, para que el back quede con el valor que se ve en pantalla.

## Orden de despliegue

1. **Antes** de subir el back: correr `database/sql/alter_product_pdf_design_products_add_combo_design.sql` en la base. El back nuevo lee `combo_design_id` en todas las consultas de diseños; sin la columna, falla.
2. Subir el back.
3. Revisar en cada combo la opción **"Hojas juntas / separadas"**. Hoy todos están en `separate` (el default), pero al comprar salía un único PDF. Después del paso 4 se va a respetar la opción: los combos que deban salir en un solo archivo hay que pasarlos a `combined` **antes**.
4. Completar los vínculos que ya existen: `php artisan pdf-designs:asignar-combos` (simulación) y, si la tabla está bien, `php artisan pdf-designs:asignar-combos --apply`. En la base local asignó 11 vínculos de los combos 172, 217 y 218. Cuando un vínculo encaja en dos combos (por ejemplo "jardín", que usa parte de las páginas de "primaria"), gana el combo donde el orden de la página coincide.
5. Subir el front con los cambios de arriba.

Mientras el front nuevo no esté, el front actual sigue creando vínculos por página **sin** combo. Esos funcionan igual que antes: un único PDF.

## Cómo probarlo

Con el combo 172 (3 páginas prestadas) y el 209 (jardín, 2 de esas páginas):

1. Abrir el 172. Tienen que aparecer los productos que ya tiene (en la base local, después del paso 4: **92940** y **93052**).
2. Abrir el 209. **No** tienen que aparecer los productos del 172.
3. En el 172, agregar un producto y **Guardar**. Tiene que seguir en la lista después de guardar y de recargar. En la base tienen que quedar 3 vínculos (en 186, 187 y 188) con `combo_design_id = 172` y `sort_order` 0, 1 y 2.
4. Agregar el mismo producto al 209 y guardar. Tienen que quedar 2 vínculos más con `combo_design_id = 209`, sin tocar los del 172.
5. Quitar el producto del 172 y guardar. Se borran solo sus 3 vínculos del 172.
6. Con el 172 en "juntas", comprar el producto: un PDF de 3 hojas. Con "separadas": 3 PDF de 1 hoja.
7. Regresión: en un diseño sin páginas prestadas, agregar y quitar productos tiene que funcionar igual que antes.
