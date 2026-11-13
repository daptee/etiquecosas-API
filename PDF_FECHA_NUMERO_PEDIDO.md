# Fecha y número de pedido en el diseñador de PDF

Dos `dynamic_field` nuevos para elementos de texto, al lado de `nombre`/`apellido`/`nombre_apellido` — para mostrar datos de la **venta**, no del cliente.

---

## Cómo marcarlo en el elemento de texto

```json
{
  "type": "text",
  "value_mode": "dynamic",
  "dynamic_field": "fecha",
  "content": "01/01/2000",
  "font_size_px": 20,
  "color": { "mode": "hex", "value": "#000000" }
}
```

| `dynamic_field` | Qué muestra | Formato |
|---|---|---|
| `fecha` | La fecha de la venta | `d/m/Y` (ej. `30/09/2026`) |
| `numero_pedido` | El id de la venta | Texto plano (ej. `100154`) |

- `content` acá es solo un texto de referencia para cuando se previsualiza/edita sin datos reales (igual que con `nombre`/`apellido`) — al generar el PDF real se ignora y se usa el dato real de la venta.
- Si `dynamic_field` no matchea ninguno de los valores conocidos (`nombre_apellido`, `nombre`, `apellido`, `fecha`, `numero_pedido`), se descarta en el servidor y se usa `content` tal cual.

---

## De dónde sale cada valor

- **`fecha`**: la fecha de la venta (`sale.created_at`, o la fecha de aprobación según el flujo) — es la misma fecha que ya se usa para agrupar los PDFs por carpeta del día.
- **`numero_pedido`**: el `id` de la venta (`sales.id`) — el mismo número que ves en `GET /sales/{id}`.

---

## Preview

El preview del editor no tiene una venta real detrás, así que por defecto muestra la fecha de hoy y `111111`. Para simularlo con datos de mentira (o de una venta real), usá los parámetros nuevos del preview — ver [PDF_PREVIEW_ICONOS.md](PDF_PREVIEW_ICONOS.md):

```
GET /product-pdf-designs/{id}/preview?fecha=2026-01-15&numeroPedido=100154
```

- `fecha` acepta cualquier formato que entienda `Carbon::parse` (`2026-01-15`, `15-01-2026`, etc.).
- `numeroPedido` es un número simple.
