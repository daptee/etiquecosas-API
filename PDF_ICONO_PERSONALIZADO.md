# Ícono personalizable en el diseñador de PDF

Cómo marcar un ícono de un diseño para que el cliente lo pueda cambiar en el checkout, y qué manda el checkout para que eso funcione. Complementa a [PDF_EDITOR.md](PDF_EDITOR.md) (que ya menciona `editable_field: "icon"` de paso) — acá está el detalle completo con ejemplos.

---

## 1. Cómo marcarlo en el diseño

Cualquier elemento `type: "icon"` puede ser personalizable agregándole estos dos campos:

```json
{
  "type": "icon",
  "icon_id": 34,
  "x_cm": 0.6,
  "y_cm": 0.6,
  "width_cm": 1.2,
  "height_cm": 1.2,
  "editable_by_customer": true,
  "editable_field": "icon"
}
```

- `icon_id`: el ícono que se ve en el editor y en el PDF **si el cliente no personaliza nada** (o si el producto ni siquiera ofrece personalización de ícono). Sigue siendo obligatorio aunque el elemento sea editable.
- `editable_by_customer: true` + `editable_field: "icon"`: le dice al backend "este ícono se reemplaza por el que elija el cliente en el checkout, si elige uno".

Un elemento de ícono **sin** esos dos campos (o con `editable_by_customer: false`) es fijo: se renderiza siempre con `icon_id`, sin importar qué mande el checkout.

---

## 2. Varios íconos editables en un mismo diseño

Si el diseño tiene más de un elemento `icon` marcado como editable (por ejemplo, un diseño de dos etiquetas iguales lado a lado, cada una con su propio ícono), **todos se reemplazan por el mismo ícono elegido** — el cliente elige un solo ícono por compra, no uno distinto por etiqueta. No hace falta (ni existe) una forma de decirle a cada elemento que tome un ícono distinto.

```json
"elements": [
  { "type": "icon", "icon_id": 18, "x_cm": 0.5, "y_cm": 0.7, "width_cm": 1.2, "height_cm": 1.2, "editable_by_customer": true, "editable_field": "icon" },
  { "type": "icon", "icon_id": 18, "x_cm": 4.9, "y_cm": 0.7, "width_cm": 1.2, "height_cm": 1.2, "editable_by_customer": true, "editable_field": "icon" }
]
```
→ ambos toman el mismo ícono del cliente si personaliza; si no personaliza, ambos muestran `icon_id: 18`.

Si en el mismo diseño hay además un ícono decorativo que **no** debe cambiar nunca (ej. un logo fijo), simplemente no le agregues `editable_by_customer` a ese elemento — convive sin problema con los que sí son editables.

---

## 3. Qué tiene que mandar el checkout

El storefront ya manda esto hoy en `customization_data` de cada producto de la venta (no es un cambio nuevo, es el mismo formato que ya se usa):

```json
{
  "icon": {
    "icon": "icons/personalization/icon_68a60f0fc4a85.svg",
    "name": "Corazón"
  }
}
```

- `icon.icon`: la ruta del archivo tal como la devuelve `GET /api/icons` (un `icon_id` del catálogo de `personalization_icons`), relativa a `public/`.
- `icon.name`: **caso especial** — si vale exactamente `"Sin dibujo"`, el backend lo interpreta como "el cliente eligió no llevar ícono" y el/los elementos editables quedan **sin ícono** (espacio en blanco), no con el `icon_id` del diseño. Si el producto permite esa opción, tiene que seguir viniendo con ese `name` literal.

Si `customization_data` no trae `icon` en absoluto (producto sin personalización de ícono), todos los elementos —editables o no— se renderizan con su `icon_id` del diseño, como si nada.

---

## 4. Cuándo mostrar el selector de ícono en el checkout

Mostralo solo si **algún** elemento del diseño publicado tiene `editable_field: "icon"`. Si ningún elemento lo tiene, no muestres el selector — elegir un ícono ahí no tendría ningún efecto en el PDF final.

---

## 5. Resumen

| Elemento | ¿Editable? | Resultado si el cliente elige un ícono | Resultado si no elige ninguno |
|---|---|---|---|
| `editable_by_customer: true, editable_field: "icon"` | Sí | Se reemplaza por el ícono del cliente (o queda vacío si `name === "Sin dibujo"`) | Se muestra `icon_id` del diseño |
| Sin esos campos (o `editable_by_customer: false`) | No | Se ignora, sigue mostrando `icon_id` del diseño | Se muestra `icon_id` del diseño |
