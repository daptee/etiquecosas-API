# Vincular/desvincular muchos productos a la vez (bulk-many)

Complementa a [PDF_DESIGNS_BULK_LINKS.md](PDF_DESIGNS_BULK_LINKS.md) — aquellos dos endpoints (`/products/bulk`) sirven para **un solo producto** con varias temáticas. Estos dos son para **varios productos a la vez**, cada uno con su propia lista de temáticas/variantes, en una sola llamada.

Ambos son `jwt.auth` (admin), igual que el resto del CRUD del editor.

---

## 1. Vincular muchos productos (y sus variantes) a la vez

`POST /api/product-pdf-designs/{id}/products/bulk-many`

- `{id}` es el id del diseño (el "generador de PDF").
- Body:

```json
{
  "links": [
    { "productId": 92900, "themeKeys": [137, 142] },
    { "productId": 52944, "themeKeys": [null] },
    { "productId": 12409, "themeKeys": [2948, 3010, 3011] }
  ]
}
```

- Cada entrada de `links` es un producto distinto con su propia lista de `themeKeys` (mismo formato que ya usa el endpoint puntual: un id de temática/variante por vínculo, o `null` si ese producto no depende de variante).
- `pageId`/`sortOrder` (opcionales, por entrada de `links`): si ese producto usa una página puntual de este diseño (no todas) — misma página para todas sus `themeKeys`. Ver [PDF_PAGINAS_COMBINADAS.md](PDF_PAGINAS_COMBINADAS.md).

### Respuesta

```json
{
  "message": "Vínculos creados",
  "data": { "...": "el diseño completo, con products actualizado" },
  "metaData": {
    "created": 5,
    "skipped": [
      { "productId": 92900, "themeKey": 142, "reason": "Ya existe un diseño vinculado a este producto y esta variante/temática" }
    ]
  }
}
```

**Importante**: si alguna combinación `productId` + `themeKey` ya estaba vinculada a este (o a otro) diseño, esa puntual se salta — **no aborta el resto**, ni de ese producto ni de los demás. Revisá `metaData.skipped` (ahora también trae `productId`, para saber cuál fue) para saber qué no se pudo crear y por qué. `metaData.created` es el total de vínculos creados, sumando todos los productos de la llamada.

---

## 2. Desvincular muchos productos (y sus variantes) a la vez

`DELETE /api/product-pdf-designs/{id}/products/bulk-many`

- Mismo body que el de arriba:

```json
{
  "links": [
    { "productId": 92900, "themeKeys": [137] },
    { "productId": 52944, "themeKeys": [null] }
  ]
}
```

- Por cada entrada de `links`, borra los vínculos de ese diseño con ese producto cuyo `theme_key` esté en su lista de `themeKeys` (incluí `null` para desvincular el vínculo "sin variante" de ese producto puntual).

### Respuesta

```json
{
  "message": "Vínculos eliminados",
  "data": { "...": "el diseño completo, con products actualizado" },
  "metaData": { "deleted": 2 }
}
```

`metaData.deleted` es el total de vínculos borrados, sumando todos los productos de la llamada. Si mandás un `productId`+`themeKey` que no estaba vinculado, simplemente no cuenta (no da error).

---

## Notas

- Estos dos endpoints **no crean ni borran el diseño en sí** — solo los vínculos con productos (tabla `product_pdf_design_products`). El diseño (`product_pdf_designs`) sigue existiendo igual que antes.
- Si tu cliente HTTP no permite mandar body en un `DELETE` (pasa con algunas configuraciones de axios/fetch por defecto), acordate de mandarlo explícitamente (en axios: `axios.delete(url, { data: { links } })`).
- Para un solo producto con varias temáticas, seguís pudiendo usar los endpoints de [PDF_DESIGNS_BULK_LINKS.md](PDF_DESIGNS_BULK_LINKS.md) (`/products/bulk`, sin `-many`) — son más simples si es un caso de un solo producto. Y para un solo vínculo puntual, los de siempre (`POST /products` / `DELETE /products/{linkId}`), documentados en [PDF_EDITOR.md](PDF_EDITOR.md).
