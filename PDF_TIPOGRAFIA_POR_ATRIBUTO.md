# Tipografía por atributo en el diseñador de PDF

Mismo mecanismo que [PDF_ICONO_PERSONALIZADO.md](PDF_ICONO_PERSONALIZADO.md), pero para que un elemento de **texto** use la tipografía que trae el valor de un atributo de la variante (ej. el atributo "Tipografía"), en vez de una tipografía fija elegida en el editor.

---

## 1. Cómo llega hoy (ya funciona, no es un cambio de datos)

Cuando se pide una venta, cada línea trae la variante con sus atributos resueltos — los de tipo `"typography"` ya incluyen el archivo de fuente real:

```json
"attributesvalues": [
  {
    "id": 400,
    "value": "universitaria",
    "font": "fonts/typographies/9/font_6a56788949a85.ttf",
    "attribute": { "id": 17, "name": "Tipografía", "type": "typography" }
  }
]
```

- `attribute.id` (acá `17`) es lo que hay que matchear desde el diseño.
- `font` es la ruta al archivo de fuente real — la resuelve el backend solo, no hay que tocar nada para que esto llegue así.

---

## 2. Cómo marcarlo en el elemento de texto

```json
{
  "type": "text",
  "content": "{{customer_name}}",
  "font_id": null,
  "dynamic_attribute_id": 17,
  "font_size_px": 40,
  "color": { "mode": "hex", "value": "#000000" }
}
```

- `dynamic_attribute_id`: el `id` del **atributo** (no del valor) — mismo campo y mismo significado que ya se usa en los elementos `icon` ([PDF_ICONO_PERSONALIZADO.md](PDF_ICONO_PERSONALIZADO.md)), ahora también soportado en elementos `text`.
- Si hay coincidencia (la variante de esa venta trae ese atributo con una fuente cargada), esa tipografía **reemplaza** a `font_id` — tiene prioridad.
- `font_id` sigue funcionando como fallback: si `dynamic_attribute_id` no matchea nada (o el elemento no lo tiene), se usa `font_id` normal, como hasta ahora.

---

## 3. Diferencia con el ícono por atributo

- Es el mismo campo (`dynamic_attribute_id`) y la misma idea, pero en un elemento `text` busca **tipografía** (`font`) en vez de **ícono** (`icon`) dentro de `attributesvalues`.
- Un mismo diseño puede tener elementos `icon` con su `dynamic_attribute_id` (ej. apuntando al atributo "Iconos") y elementos `text` con el suyo (ej. apuntando al atributo "Tipografía") — son independientes entre sí.

---

## 4. Preview

Para ver esto en la vista previa del editor, usá el mismo `?variantId=` de [PDF_PREVIEW_ICONOS.md](PDF_PREVIEW_ICONOS.md) — carga una variante real, así resuelven tanto los íconos como las tipografías por atributo de esa variante puntual:

```
GET /product-pdf-designs/{id}/preview?variantId=3072
```

---

## 5. Resumen

| Elemento | Campo | Prioridad | Fallback si no matchea |
|---|---|---|---|
| `text` con `dynamic_attribute_id` | busca `font` en `attributesvalues` | por sobre `font_id` | `font_id` fijo del elemento |
| `icon` con `dynamic_attribute_id` | busca `icon` en `attributesvalues` | por sobre el ícono libre del checkout | ícono libre (`customization_data.icon`), luego `icon_id` fijo |
