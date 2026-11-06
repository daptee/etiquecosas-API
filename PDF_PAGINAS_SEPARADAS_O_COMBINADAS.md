# Un diseño con varias páginas: ¿PDF separado por página, o un solo PDF?

Cuando un diseño (`product_pdf_designs`) tiene 2 o más páginas en `data.pages[]`, ahora se puede configurar cómo sale el PDF final al descargarlo o al generarse por una venta:

| `pagesOutputMode` | Resultado |
|---|---|
| `separate` (default) | Un PDF **por página**, cada uno con `-1`, `-2`, `-3`... al final del nombre del archivo. |
| `combined` | Todas las páginas juntas en un **único PDF** (una página de PDF por cada página del diseño). |

Esto es puramente sobre **cómo se arma el archivo de salida**, no cambia nada del diseño en sí ni de cómo se dibuja cada página — con cualquiera de los dos modos, cada página mantiene su propio tamaño de hoja, sus propios elementos, etc.

Un diseño de **una sola página** no se ve afectado por este campo — siempre sale en un solo PDF, como siempre.

---

## Cómo configurarlo

`POST /api/product-pdf-designs/{id}` (el mismo endpoint de guardar de siempre), campo nuevo y opcional:

```json
{
  "pagesOutputMode": "combined"
}
```

Si no se manda, queda (o se mantiene) en `"separate"`.

---

## Dónde aplica

- `GET /product-pdf-designs/{id}/preview` (botón "Descargar" del editor): con `separate` y 2+ páginas, devuelve un **`.zip`** con un PDF por página (una respuesta HTTP solo puede traer un archivo). Con `combined`, devuelve el PDF único de siempre.
- Generación real de una venta: con `separate`, se guardan varios archivos en `storage/app/pdf/planchas/{fecha}/`, uno por página, visibles todos en `GET /pdf-directories/{fecha}`. Con `combined`, se guarda uno solo, como antes de este cambio.

**No afecta** a [PDF_PAGINAS_COMBINADAS.md](PDF_PAGINAS_COMBINADAS.md) (combinar páginas puntuales de **varios diseños distintos** en un mismo producto+variante vía `pageId`/`sortOrder`) — ese camino sigue generando siempre un único PDF, sin importar `pagesOutputMode` de los diseños involucrados.

---

## Antes de poder usarlo en un entorno

Hace falta aplicar `database/sql/alter_product_pdf_designs_add_pages_output_mode.sql` en la base de ese entorno (agrega la columna `pages_output_mode`, default `'separate'`, a `product_pdf_designs`).
