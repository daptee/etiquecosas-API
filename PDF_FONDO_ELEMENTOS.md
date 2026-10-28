# Fondo de elementos en diseños PDF (`fill`): cómo quedó en el back

Respuesta del back al pedido del front (el `PDF_FONDO_ELEMENTOS.md` original, formato "DESPUÉS").
Todo lo que el doc pedía en "Para el back" ya está implementado. Este doc cuenta **qué cambió** y
**cómo se comporta ahora**, incluidas las diferencias con el pedido.

Relacionados: [PDF_FONDO_PAGINA.md](PDF_FONDO_PAGINA.md) (fondo de la página, **sin cambios**) y
[PDF_IMAGENES_ETIQUETA_E_ICONO.md](PDF_IMAGENES_ETIQUETA_E_ICONO.md) (endpoint de subida de imagen, **sin cambios**).

---

## Resumen

| | Antes | Ahora |
|---|---|---|
| Quién puede tener fondo | Sólo la etiqueta (`type: "background"`) | Etiqueta, ícono, texto y forma |
| Cómo se manda | `color` y `background_image` sueltos (podían venir los dos) | Un objeto `fill` con una sola opción activa |
| Etiqueta sin color | El PDF la pintaba **blanca** | **Transparente** |
| Imagen en forma `custom` | Se ignoraba | Se dibuja **recortada con el contorno** |
| Importar desde PDF/producto | Sin `fill` | Cada elemento sale con `fill` |

---

## El contrato

Mismo endpoint de siempre: `POST /product-pdf-designs` y `POST /product-pdf-designs/{id}`, dentro de
`data.pages[].elements[]`.

```ts
fill?:
  | { type: "none" }
  | { type: "color", color: { mode: "hex" | "cmyk", value: string, dynamic_attribute_id: number | null, editable_by_customer: boolean } }
  | { type: "image", image: string }   // path de POST /product-pdf-designs/{id}/element-image
```

### Prioridad

1. **Si el elemento trae `fill`, manda `fill`.** En la etiqueta se ignoran `color` y `background_image`.
2. **Si no trae `fill`** (diseños guardados antes del cambio):
   - Etiqueta: `background_image` → imagen; si no, `color` → color; si no, transparente.
   - Ícono / texto / forma: sin fondo.

> En texto y forma, `color` **sigue siendo** el color de la letra / de la forma. Nunca se usa como fondo.

Consecuencia para el front: **ya no hace falta mandar el espejo** `color` / `background_image` en la
etiqueta. Si se sigue mandando no pasa nada (gana `fill`), así que se puede sacar cuando convenga.

---

## Validación al guardar

**422** (error de validación de Laravel) cuando:

| Caso | Campo del error |
|---|---|
| `fill` no es un objeto | `data.pages.N.elements.M.fill` |
| Falta `type` o no es `none` / `color` / `image` | `…fill.type` |
| `type: "color"` sin `color`, o `color` sin `value` | `…fill.color` / `…fill.color.value` |
| `color.mode` distinto de `hex` / `cmyk` | `…fill.color.mode` |
| `type: "image"` sin `image`, o que no empieza con `pdf-element-images/` | `…fill.image` |

Después de validar, el back **limpia** el `fill` antes de guardarlo:

- Se guarda sólo la opción activa: un `{ type: "color", color, image }` se guarda sin `image`.
- `dynamic_attribute_id` se guarda como número o `null`, y `editable_by_customer` como booleano.
- Un path de imagen con forma rara (`pdf-element-images/99/../../.env`, nombre que empieza con `.`)
  se guarda como `{ "type": "none" }`.
- `fill: null` se borra del elemento (equivale a no mandarlo).

> **Diferencia con el pedido:** no se exige que la imagen sea del **mismo** diseño
> (`pdf-element-images/<designId>/`), sólo que sea de `pdf-element-images/<número>/`. Así no se rompen
> los diseños duplicados ni las páginas tomadas de otro diseño, que traen paths del diseño original.

---

## Cómo se dibuja en el PDF

### Etiqueta (`background`)

- **Color:** relleno de la etiqueta con su forma (esquinas por `radius_mode` / `radius_pct`, círculo, o
  contorno `custom`). Igual que antes.
- **Imagen:** estirada al 100% × 100% de la caja (sin mantener proporción), recortada con la forma.
- **Transparente:** no se pinta nada; si tiene `border`, el borde se dibuja igual (sirve para etiquetas
  que son sólo línea de corte).
- El **borde** queda encima del fondo, como antes. En formas `custom` el borde se sigue ignorando
  (eso no cambió).

### Ícono / texto / forma

- El fondo ocupa **la caja completa** del elemento (`x_cm`, `y_cm`, `width_cm`, `height_cm`), **rota con
  él** (`rotation_deg`) y queda **debajo** del texto o del ícono.
- Recorte rectangular, sin esquinas redondeadas.
- Respeta el `z_index` del elemento respecto de los demás.

### Imagen en forma `custom` (nuevo)

dompdf no puede recortar una imagen con un contorno libre, así que el back arma un **PNG ya
recortado**: la imagen estirada a la caja, a 300 DPI, transparente fuera del `outline_svg` y con el
borde suavizado. Queda cacheado en `storage/app/pdf-fill-cache` (no va a git): la misma etiqueta
repetida en la hoja o en otros pedidos no se vuelve a procesar.

Si no se puede recortar, la imagen **sale entera, sin recorte** (queda un aviso `[PDF-FILL]` en el log):

- el contorno tiene curvas o arcos (sólo se entienden comandos `M`, `L`, `H`, `V`, `Z`);
- la imagen es **SVG** (la librería de imágenes del servidor no lee SVG).

### Color elegido por el cliente

Cuando el elemento tiene `editable_by_customer: true` y `editable_field: "color"`:

- **Etiqueta:** el color del cliente **reemplaza el fondo**, salvo que el fondo sea una imagen (antes la
  imagen también tapaba el color).
- **Texto:** cambia el color de la letra, **no** el fondo.

### Imagen que no existe en disco

El elemento sale **sin fondo** (antes quedaba una imagen rota) y se registra un aviso `[PDF-FILL]` en el log.

---

## Importación

`POST /product-pdf-designs/import-from-pdf` y la importación desde producto ahora devuelven `fill` en
**cada** elemento:

- Etiquetas: `fill` derivado de su color (`{ type: "color", color }`). Siguen trayendo también `color`.
- Íconos y textos: `{ "type": "none" }`.

---

## Cambios de comportamiento a tener en cuenta

1. **Etiqueta vieja sin `color` ahora es transparente.** Antes el PDF la pintaba blanca aunque el editor
   la mostrara transparente. Sobre una página blanca no se nota; sobre una página con fondo de color o
   imagen, sí.
2. **Formas `custom` con `background_image` viejo:** antes la imagen se ignoraba (el editor no dejaba
   elegirla, pero podía haber quedado guardada al cambiar de forma). Ahora se dibuja.
3. **`fill.color.dynamic_attribute_id` se guarda pero todavía no cambia el color en el PDF.** Es lo mismo
   que pasa hoy con `color`: el único color variable que se aplica es el que elige el cliente.

---

## Pendiente de verificar

- Generar un PDF real desde el editor y mirarlo: en el servidor de desarrollo no hay herramienta para
  convertir el PDF a imagen, así que se revisó el HTML que arma dompdf y el PNG recortado, no el PDF
  dibujado.
- Probar el recorte con una forma `custom` real: la de la base local (id 2) no tiene `outline_svg`;
  se probó con un hexágono armado a mano.

---

## Archivos tocados

| Archivo | Qué hace |
|---|---|
| `app/Services/PdfDesignSanitizer.php` | `sanitizeFill()` (limpieza) y `fillDelElemento()` (prioridad `fill` / formato viejo) |
| `app/Http/Controllers/ProductPdfDesignController.php` | Reglas de validación de `fill`; `fill` en las importaciones |
| `app/Services/EtiquetaService.php` | `resolverFill()`: resuelve el fondo de cada elemento, color del cliente, path absoluto |
| `app/Services/PdfFillImage.php` | **Nuevo.** PNG recortado con el contorno `custom`, con cache |
| `app/Services/CutLineGeometry.php` | `trazadoCustom()` pasó a público para reusar el parser de contornos |
| `resources/views/tematica/editor/RENDER.blade.php` | Dibuja el fondo de etiqueta, ícono, texto y forma |
| `tests/Unit/PdfFillTest.php` | **Nuevo.** Tests de limpieza y prioridad |
