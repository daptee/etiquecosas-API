# Vincular/desvincular muchas temáticas a la vez (bulk)

Complementa a los endpoints puntuales `POST /product-pdf-designs/{id}/products` y `DELETE /product-pdf-designs/{id}/products/{linkId}` (documentados en `PDF_EDITOR.md`) — estos dos nuevos hacen lo mismo pero para **varias temáticas de una sola vez**, en vez de una llamada por cada una.

Ambos son `jwt.auth` (admin), igual que el resto del CRUD del editor.

---

## 1. Vincular muchas temáticas a la vez

`POST /api/product-pdf-designs/{id}/products/bulk`

- `{id}` es el id del diseño (el "generador de PDF").
- Body:

```json
{
  "productId": 92900,
  "themeKeys": [137, 142, 150]
}
```

- `productId`: el producto al que se le van a vincular todas esas temáticas.
- `themeKeys`: array de ids de temática/variante (los mismos `theme_key` que ya se usan en el vínculo puntual). Si alguna temática de ese producto no depende de variante, poné `null` en ese lugar del array.
- `pageId`/`sortOrder` (opcionales): si el vínculo es para una página puntual de este diseño (no todas) — misma página para todas las `themeKeys` de esta llamada. Ver [PDF_PAGINAS_COMBINADAS.md](PDF_PAGINAS_COMBINADAS.md).

### Respuesta

```json
{
  "message": "Vínculos creados",
  "data": { "...": "el diseño completo, con products actualizado" },
  "metaData": {
    "created": 2,
    "skipped": [
      { "themeKey": 150, "reason": "Ya existe un diseño vinculado a este producto y esta variante/temática" }
    ]
  }
}
```

**Importante**: si alguna combinación `productId` + `themeKey` ya estaba vinculada a este (o a otro) diseño, esa puntual se salta — **no aborta el resto**. Revisá `metaData.skipped` para saber cuáles no se pudieron crear y por qué. `metaData.created` es cuántas sí se crearon.

---

## 2. Desvincular muchas temáticas a la vez

`DELETE /api/product-pdf-designs/{id}/products/bulk`

- Mismo body que el de arriba:

```json
{
  "productId": 92900,
  "themeKeys": [137, 142]
}
```

- Borra **todos** los vínculos de ese diseño con ese producto cuyo `theme_key` esté en la lista.
- Para desvincular también el vínculo "sin variante" (`theme_key: null`), incluí `null` en el array: `"themeKeys": [137, null]`.

### Respuesta

```json
{
  "message": "Vínculos eliminados",
  "data": { "...": "el diseño completo, con products actualizado" },
  "metaData": { "deleted": 2 }
}
```

`metaData.deleted` es cuántos vínculos se borraron de verdad — si mandás un `themeKey` que no estaba vinculado, simplemente no cuenta (no da error).

---

## Notas

- Estos endpoints **no crean ni borran el diseño en sí** — solo los vínculos con productos (tabla `product_pdf_design_products`). El diseño (`product_pdf_designs`) sigue existiendo igual que antes.
- Si tu cliente HTTP no permite mandar body en un `DELETE` (pasa con algunas configuraciones de axios/fetch por defecto), acordate de mandarlo explícitamente (en axios: `axios.delete(url, { data: { productId, themeKeys } })`).
- Para el caso de una sola temática seguís pudiendo usar los endpoints puntuales (`POST /products` / `DELETE /products/{linkId}`) — los bulk son solo para cuando hay que tocar varias de una.
