# Imagen propia de una etiqueta e ícono con imagen custom

Dos cosas nuevas, separadas de [PDF_FONDO_PAGINA.md](PDF_FONDO_PAGINA.md) (que es para la **página entera**) — esto es para **un elemento puntual** del diseño.

---

## 1. Subir la imagen

`POST /api/product-pdf-designs/{id}/element-image` (multipart, `jwt.auth`)

```
image: <archivo> (jpg, jpeg, png, webp o svg — máximo 8MB)
```

### Respuesta

```json
{
  "message": "Imagen subida",
  "data": { "path": "pdf-element-images/6/el_68a60f0f.jpg" }
}
```

Mismo patrón que el de fondo de página: este endpoint **solo sube el archivo** y devuelve el `path` — no toca el diseño ni ninguna otra tabla. Guardá ese `path` vos mismo en el campo que corresponda (ver abajo) al hacer el `POST /product-pdf-designs/{id}` de actualización.

**Importante**: esto **no crea nada en `personalization_icons`** — a diferencia de subir un ícono por `POST /icons` (que sí queda en el catálogo compartido y aparece en `GET /api/icons` para cualquier diseño), una imagen subida por acá es propia de ESTE diseño y este elemento puntual, y nunca aparece en el catálogo de íconos.

---

## 2. Imagen de fondo de una etiqueta puntual

Va en un elemento `type: "background"`, campo `background_image`:

```json
{
  "type": "background",
  "x_cm": 1, "y_cm": 1, "width_cm": 4, "height_cm": 2.5,
  "color": { "mode": "hex", "value": "#FCE4E6" },
  "background_image": "pdf-element-images/6/el_68a60f0f.jpg"
}
```

- Si `background_image` está presente, **reemplaza** el color (`color` queda ignorado para el relleno, pero no hace falta sacarlo del JSON).
- La imagen se recorta al mismo `border-radius`/forma que ya tenía el elemento (esquinas redondeadas, etc. — mismo recorte que usaría el color).
- **No soportado**: formas `custom` (`label_shape_id` con `outline_svg`) — ahí `background_image` se ignora, solo funciona en los rectángulos/círculos normales.

---

## 3. Ícono con imagen custom (no del catálogo)

Va en un elemento `type: "icon"`, campo nuevo `custom_icon_path`:

```json
{
  "type": "icon",
  "x_cm": 0.5, "y_cm": 0.5, "width_cm": 1.5, "height_cm": 1.5,
  "icon_id": null,
  "custom_icon_path": "pdf-element-images/6/el_68a60f0f.svg"
}
```

### Prioridad (de mayor a menor)

1. Ícono por atributo de la variante (`dynamic_attribute_id`, ver [PDF_ICONO_PERSONALIZADO.md](PDF_ICONO_PERSONALIZADO.md))
2. Ícono "libre" del checkout (`editable_by_customer` + `editable_field: "icon"`)
3. **`custom_icon_path`** (nuevo)
4. `icon_id` (catálogo `personalization_icons`)

O sea: `custom_icon_path` es una alternativa a `icon_id` — el ícono fijo que se usa cuando nada más (personalización del cliente, atributo de variante) lo reemplaza. Si el elemento tiene los dos (`icon_id` y `custom_icon_path`), gana `custom_icon_path`.

---

## Resumen

| Dónde | Campo | Reemplaza a |
|---|---|---|
| `sheet` (página entera) | `background_image` | nada, es nuevo (ver [PDF_FONDO_PAGINA.md](PDF_FONDO_PAGINA.md)) |
| elemento `background` | `background_image` | el `color` de ESE elemento |
| elemento `icon` | `custom_icon_path` | el `icon_id` de ESE elemento |
