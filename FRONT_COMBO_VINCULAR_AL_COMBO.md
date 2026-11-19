# Combos: el producto se vincula al combo, no a los diseños de origen

## Qué pasa hoy

Un **combo** es un diseño que toma páginas de otros diseños (`data.pages[].source`). Por ejemplo, el **172 "Combo primaria Perritos y Gatitos"** usa una página de cada uno de estos diseños:

- 186 Objetos
- 187 Lápices
- 188 Ropa

Cuando a un combo se le agrega un producto, el editor no lo vincula al combo. Crea **un vínculo por página en cada diseño de origen**:

| Diseño | page_id | sort_order |
|---|---|---|
| 186 | página de Objetos | 0 |
| 187 | página de Lápices | 1 |
| 188 | página de Ropa | 2 |

El combo 172 queda **sin ningún vínculo propio**. Esto trae tres problemas:

1. **La opción de hojas del combo no se usa al comprar.** El PDF del cliente se arma con esas 3 páginas sueltas y siempre sale en un único PDF, aunque el 172 diga "separadas".
2. **El combo no muestra sus productos.** Esos productos aparecen en otros combos que usan las mismas páginas, por ejemplo el 209 "Combo jardín", que usa 2 de esas 3 páginas.
3. **Quitar un producto de "Objetos" puede romper el combo**, porque borra también la página que el combo tenía vinculada ahí.

## Cómo tiene que quedar

Un combo se vincula **igual que cualquier diseño**: un vínculo al combo mismo, sin `pageId`.

| Diseño | page_id | sort_order |
|---|---|---|
| 172 | null | 0 |

El back ya lo resuelve todo al comprar, sin cambios del lado del front:

- completa las páginas prestadas con el contenido actual de 186/187/188;
- usa la opción **"hojas juntas / separadas" del 172**;
- nombra el archivo con el nombre del combo.

Esto ya está probado con el combo 217 y una venta real: en "separadas" salieron 3 PDF de 1 hoja, y en "juntas", 1 PDF de 3 hojas.

**No hay endpoints nuevos ni campos nuevos.** Se usan los mismos `bulk-attach-many` y `bulk-detach-many` de siempre, sin `pageId`.

## Cambios en el front

Todo está en `pages/productPdfDesigns/edit/[id].vue`.

### 1. `syncLinks`: vincular siempre el diseño entero y no tocar los diseños de origen

Hoy, si el diseño tiene alguna página con `source` (`perPage`), hace dos cosas:

- vincula las páginas propias una por una;
- para cada diseño de origen (`sourceWork`) carga ese diseño y le crea o borra vínculos por página.

Hay que **sacar todo eso**. El diseño se vincula siempre entero, tenga o no páginas prestadas:

```ts
const syncLinks = async (designId: number, currentLinks: ProductPdfDesignVariantLink[]) => {
	const combos = currentCombos();
	const allCombos = [
		...combos,
		...linkBaseline.combos.filter(
			b => !combos.some(c => c.productId === b.productId && c.themeKey === b.themeKey)
		)
	];
	const inCombos = (l: LinkCombo) =>
		allCombos.some(c => c.productId === l.productId && c.themeKey === l.themeKey);
	const skipped: SkippedLink[] = [];

	// Siempre un vínculo al diseño entero, aunque tenga páginas de otros diseños: el back completa
	// las páginas prestadas al comprar y usa el pages_output_mode de ESTE diseño.
	const desiredOwn: LinkKey[] = combos.map(combo => ({ ...combo, pageId: null, sortOrder: 0 }));
	// Los vínculos por página que hubiera de antes también se comparan: si no están en desiredOwn,
	// se borran (quedan reemplazados por el vínculo entero).
	const existingOwn = currentLinks.filter(l => inCombos(l));
	const ownDelete = existingOwn.filter(l => !desiredOwn.some(d => linkKey(d) === linkKey(l)));
	const ownAdd = desiredOwn.filter(d => !existingOwn.some(l => linkKey(l) === linkKey(d)));

	// --- unlink (igual que hoy, sin el bucle de sourceWork)
	// ... bloque `if (ownDelete.length) { ... }` sin cambios ...

	// --- link (igual que hoy)
	// ... `const linkAll = ...` sin cambios ...
	await linkAll(designId, ownAdd);

	return skipped;
};
```

Lo que se borra de la función actual:

- `const pages` y `const perPage`;
- la rama `perPage ? pages.map(...)` de `desiredOwn`;
- `wasPerPage`;
- todo el bloque `// --- the links of each design whose pages this one takes`, o sea `sourceDesignIds`, `sourceWork` y el `for` que llama a `loadDesign`;
- `for (const work of sourceWork) { ... unlinkVariant ... }`;
- `for (const work of sourceWork) await linkAll(...)`.

Hay que actualizar también el comentario de arriba de la función. Ya no aplica "each page taken from another design is linked in THAT design at the position it has here".

### 2. `pendingProductsFromDesign`: un producto es del diseño si tiene vínculo entero

Hoy, en un combo, un producto cuenta como del diseño solo si están vinculadas **todas sus páginas propias**. El combo 172 no tiene páginas propias, así que nunca muestra productos. Hay que dejarlo así:

```ts
const pendingProductsFromDesign = (
	design: ProductPdfDesign,
	keep: PdfPendingProduct[] = []
): PdfPendingProduct[] => {
	// Un producto + variante es de este diseño si tiene un vínculo al diseño entero (sin pageId),
	// tenga o no páginas de otros diseños.
	const isOwn = (productId: number, themeKey: number | null) =>
		design.variantLinks.some(
			link => link.productId === productId && link.themeKey === themeKey && link.pageId == null
		);
	// ... resto igual ...
```

Hay que sacar `ownPageIds` y `perPage`, y actualizar el comentario de arriba.

### 3. `linkBaseline.stubs` y `captureLinkBaseline`

`stubs` solo lo usaba `syncLinks` para encontrar los vínculos por página en los diseños de origen. Se puede sacar, dejando `linkBaseline = { combos: [] }` y `captureLinkBaseline` sin `stubs`.

`loadDesign` (de `useBorrowedPages`) se sigue usando para mostrar las páginas prestadas en el editor, así que no hay que borrarlo. Solo deja de usarse dentro de `syncLinks`.

### Lo que NO cambia

- Cómo se agregan páginas de otros diseños y cómo se muestran en el editor (solo lectura).
- La opción "Hojas: juntas / separadas" (`pagesOutputMode`), que ya se manda bien al guardar.
- Los diseños sin páginas prestadas, que ya se vinculaban enteros.

## Productos que ya estaban vinculados

El back tiene un comando que convierte los vínculos viejos (uno por página en los diseños de origen) en un vínculo al combo:

```
php artisan pdf-designs:vincular-combos           # muestra qué haría
php artisan pdf-designs:vincular-combos --apply   # lo aplica
```

Hay que correrlo **antes** de subir este front. Si no, el front nuevo no reconoce los vínculos viejos como productos del combo y al guardar no los borra.

## Cómo probarlo

1. Abrir el combo **172**. Tienen que aparecer sus productos en el panel, por ejemplo 92940 y 93052.
2. Agregar un producto nuevo y guardar. En la base tiene que quedar **una sola** fila: `product_pdf_design_id = 172`, `page_id = NULL`. No tienen que aparecer filas nuevas en 186, 187 ni 188.
3. Abrir **186 Objetos**. El producto del paso 2 **no** tiene que aparecer ahí.
4. Abrir el **209 Combo jardín**. Los productos del 172 **no** tienen que aparecer ahí.
5. Quitar el producto del 172 y guardar. Se tiene que borrar esa única fila.
6. Cambiar la opción de hojas del 172 a "juntas", comprar ese producto, y comprobar que sale 1 PDF con 3 hojas.
