# Directorios de PDF — ver, editar, regenerar

Agregado al módulo de directorios (`/pdf-directories`, `PdfDirectoryController`/`PdfDirectoryService`) para que cada PDF de un pedido se pueda ver sin descargar, saber a qué diseño del editor corresponde (para ir a editarlo) y regenerarse individualmente, sin tener que regenerar todo el pedido.

Aplica solo a `pdfs_pedidos` (los PDF de una línea de venta puntual) — **no** a `extras` (cintas de coser/planchar, bandas, sellos: son archivos consolidados por fecha, sin una línea de venta propia detrás).

---

## 1. `design_id` en cada PDF de `GET /pdf-directories/{fecha}`

Cada item de `pdfs_pedidos` ahora trae un campo más:

```json
{
  "nombre": "97219-7943-Combo ESCOLAR-EMOJIS-1.pdf",
  "ruta": "...",
  "tamanio": 58453,
  "fecha_modificacion": "2026-01-01 18:45:01",
  "design_id": 17
}
```

| Campo | Descripción |
|---|---|
| `design_id` | `product_pdf_designs.id` del diseño del editor vinculado HOY al producto+variante que generó este PDF. `null` si ese producto usa el sistema legacy (sin diseño armado en el editor) — con eso alcanza para decidir si mostrar el botón "editar". |

**Cómo se calcula**: el nombre del archivo siempre arranca `"{ventaId}-{salesProductId}-..."` — de ahí se saca la línea de venta (`sales_products`), su producto+variante, y se repite la MISMA búsqueda que usa `ProductPdfResolverService::resolveAndGenerate()` al generar un PDF real (`product_pdf_design_products`, con el diseño publicado y activo).

**Es "mejor esfuerzo", no una foto histórica**: nada guarda qué diseño estaba vinculado en el momento EXACTO en que ese PDF puntual se generó — `design_id` siempre refleja el vínculo ACTUAL. Si el vínculo cambió después de generar ese PDF (se reemplazó el diseño, se despublicó, etc.), `design_id` puede no ser el que realmente se usó en su momento. En la práctica esto no es un problema: el botón "editar" lo que quiere es llevar al admin al diseño que correspondería a ese pedido HOY, no reconstruir historia.

---

## 2. Ver un PDF sin descargar

```
GET /pdf-directories/{fecha}/view/{nombrePdf}
```

Mismo archivo y mismos permisos que `GET /pdf-directories/{fecha}/download/{nombrePdf}` (un diseñador solo puede ver los PDFs de sus pedidos asignados) — la única diferencia es que responde sin `Content-Disposition: attachment`, así que el navegador lo muestra inline (vista previa) en vez de forzar la descarga.

---

## 3. Regenerar un PDF puntual

```
POST /pdf-directories/{fecha}/regenerate/{nombrePdf}
```

Regenera SOLO la línea de venta de ese PDF (no todo el pedido): borra los archivos de esa línea (`{ventaId}-{salesProductId}-*.pdf`) y vuelve a correr `ProductPdfResolverService::resolveAndGenerate()` — el mismo punto único que usa una venta real, así que toma el diseño del editor vinculado HOY (o cae al sistema legacy si no hay ninguno), con los mismos datos de personalización (`nombre`/`color`/`ícono`) que ya tenía esa línea en `sales_products.customization_data`.

```json
{
  "success": true,
  "message": "PDF regenerado correctamente",
  "data": { "paths": ["97219-7943-Combo ESCOLAR-EMOJIS-1.pdf"] }
}
```

Si el producto de esa línea no tiene ni diseño ni temática asociada, devuelve error 500 con el detalle.

**Nota**: si el diseño/temática de ese producto genera más de una página, el nombre resultante puede traer más archivos que el que se clickeó originalmente (`-1.pdf`, `-2.pdf`, ...) — "regenerar este PDF" en la práctica regenera todos los de esa línea de venta, no un único archivo suelto.

---

## Permisos

Mismo criterio que el resto del módulo: un diseñador (profile_id 2) solo puede ver/regenerar PDFs de pedidos (`sales.user_id`) asignados a él; un admin puede todo.
