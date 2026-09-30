# Combinar páginas de distintos diseños en un mismo producto+variante

Hasta ahora, un vínculo (`product_pdf_design_products`) apuntaba a **un diseño entero**: al generar el PDF, se usaban TODAS las páginas de ese diseño. Ahora un producto+variante puede armarse combinando **páginas puntuales de distintos diseños**, en un orden específico — para los productos que "toman prestada" una página de otro PDF para completarse.

---

## Los dos campos nuevos del vínculo

| Campo | Qué hace |
|---|---|
| `pageId` | El `id` de una página puntual de `data.pages[]` de ESE diseño (ej. `"page-1790623562410-8789"`). Si no se manda (o es `null`), usa **todas** las páginas de ese diseño — comportamiento exactamente igual que antes. |
| `sortOrder` | Un número para ordenar las páginas cuando un mismo producto+variante tiene **varios** vínculos. Menor va primero. Default `0`. |

Van en el mismo body que ya usan `POST /product-pdf-designs/{id}/products`, `.../products/bulk` y `.../products/bulk-many` (documentados en [PDF_EDITOR.md](PDF_EDITOR.md), [PDF_DESIGNS_BULK_LINKS.md](PDF_DESIGNS_BULK_LINKS.md):
- `productId`: el producto al que se le van a vincular todas esas temáticas.
- `themeKeys`: array de ids de temática/variante (los mismos `theme_key` que ya se usan en el vínculo puntual). Si alguna temática de ese producto no depende de variante, poné `null` en ese lugar del array.
- `pageId`/`sortOrder` (opcionales): si el vínculo es para una página puntual de este diseño (no todas) — misma página para todas las `themeKeys` de esta llamada. Ver [PDF_PAGINAS_COMBINADAS.md](PDF_PAGINAS_COMBINADAS.md).


y [PDF_DESIGNS_BULK_MANY_LINKS.md](PDF_DESIGNS_BULK_MANY_LINKS.md)) — los tres endpoints ya los aceptan: 
- Cada entrada de `links` es un producto distinto con su propia lista de `themeKeys` (mismo formato que ya usa el endpoint puntual: un id de temática/variante por vínculo, o `null` si ese producto no depende de variante).
- `pageId`/`sortOrder` (opcionales, por entrada de `links`): si ese producto usa una página puntual de este diseño (no todas) — misma página para todas sus `themeKeys`. Ver [PDF_PAGINAS_COMBINADAS.md](PDF_PAGINAS_COMBINADAS.md).

---

## Ejemplo: un producto que combina página de 2 diseños

Un producto (`productId: 500`, variante `themeKey: 3067`) necesita: la página 2 del diseño A (id `10`) primero, y después TODA la única página del diseño B (id `20`).

**1. Vincular la página puntual del diseño A:**

```
POST /api/product-pdf-designs/10/products
{ "productId": 500, "themeKey": 3067, "pageId": "page-A2", "sortOrder": 0 }
```

**2. Vincular el diseño B completo (sin `pageId`, sigue trayendo todas sus páginas):**

```
POST /api/product-pdf-designs/20/products
{ "productId": 500, "themeKey": 3067, "sortOrder": 1 }
```

Al generar el PDF de una venta con ese producto+variante, el resultado va a tener: página 2 del diseño A, seguida de las páginas del diseño B — en ese orden, en un solo PDF.

---

## Qué NO cambia

- Un producto+variante con **un solo vínculo sin `pageId`** (el caso de siempre) sigue funcionando exactamente igual — no hace falta tocar nada de lo ya cargado.
- `GET /product-pdf-designs/{id}/preview` sigue previsualizando **ese diseño entero** (todas sus páginas) — no arma combinaciones de varios diseños, porque el preview es "cómo se ve este diseño", no "cómo queda el PDF final de tal producto". Para ver el resultado combinado real hay que generar el PDF de una venta (o de prueba) con ese producto+variante.

---

## Restricción a nivel base de datos

Ya no existe el límite de "un solo vínculo por producto+variante" — ahora la restricción es `(productId, themeKey, designId, pageId)`: no se puede crear el MISMO vínculo (mismo producto, variante, diseño Y página) dos veces, pero sí varios vínculos distintos para el mismo producto+variante (justamente para poder combinarlos).
