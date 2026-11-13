# Color e imagen de fondo de la página (hoja completa)

Nuevo, separado de los elementos `background` (que son fondos de UNA etiqueta puntual dentro de la hoja): esto es el fondo de **toda la hoja física** — el `sheet` de cada página.

---

## 1. Subir la imagen de fondo

`POST /api/product-pdf-designs/{id}/background-image` (multipart, `jwt.auth`)

```
image: <archivo> (jpg, jpeg, png, webp o svg — máximo 8MB)
```

### Respuesta

```json
{
  "message": "Imagen de fondo subida",
  "data": { "path": "pdf-backgrounds/6/bg_68a60f0f.jpg" }
}
```

Guardá ese `path` — es lo que va en `sheet.background_image` (ver abajo). Este endpoint **solo sube el archivo**, no toca el diseño — el `path` hay que guardarlo vos mismo dentro de `data` al hacer el `POST /product-pdf-designs/{id}` de actualización.

---

## 2. Campos en `sheet`

Van dentro de `data.pages[].sheet`, al lado de `width_cm`/`height_cm`:

```json
{
  "sheet": {
    "width_cm": 18.5,
    "height_cm": 29,
    "background_color": { "mode": "hex", "value": "#FCE4E6" },
    "background_image": "pdf-backgrounds/6/bg_68a60f0f.jpg"
  }
}
```

| Campo | Obligatorio | Descripción |
|---|---|---|
| `background_color` | no | `{ "mode": "hex" \| "cmyk", "value": "..." }` — mismo formato que el `color` de cualquier elemento |
| `background_image` | no | La ruta que devolvió el endpoint de subida. Cubre toda la hoja (`object-fit: cover`, se recorta si la proporción no coincide exacto con `width_cm`/`height_cm`) |

Los dos son independientes y opcionales — podés usar solo color, solo imagen, los dos juntos, o ninguno (como hasta ahora).

---

## 3. Orden de apilado

El fondo de página siempre queda **detrás de todos los elementos de esa hoja**, sin importar el `z_index` que tengan (aunque sea `0` o no tengan `z_index`). Entre los dos fondos: primero se pinta `background_color`, encima `background_image` (si hay imagen y no es transparente, tapa el color — el color solo se ve en las partes transparentes de una imagen, ej. un PNG con transparencia).

Verificado generando un PDF real y leyendo el orden de los operadores de dibujo (no a simple vista): color de fondo → imagen de fondo → elementos del diseño, en ese orden, de atrás hacia adelante.

---

## 4. Ejemplo completo

```json
{
  "pages": [
    {
      "sheet": {
        "width_cm": 18.5,
        "height_cm": 29,
        "background_color": { "mode": "hex", "value": "#F5F5F5" },
        "background_image": "pdf-backgrounds/6/bg_68a60f0f.jpg"
      },
      "elements": [
        { "type": "background", "x_cm": 1, "y_cm": 1, "width_cm": 4, "height_cm": 2.5, "color": { "mode": "hex", "value": "#FFFFFF" } }
      ]
    }
  ]
}
```
