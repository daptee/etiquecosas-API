# Importar un diseño desde un PDF ya generado

Endpoint para subir un PDF de hoja de etiquetas **generado por este mismo sistema** (el sistema viejo, `product_pdf` / las vistas por temática en `resources/views/tematica/*`) y armar automáticamente un `ProductPdfDesign` nuevo a partir de lo que haya en ese PDF: fondos, íconos y textos, con su posición, tamaño y color reales. Pensado para no tener que cargar a mano, elemento por elemento, diseños que ya existían como PDF suelto.

---

## Endpoint

```
POST /product-pdf-designs/import-from-pdf
Content-Type: multipart/form-data
```

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `pdf` | file | Sí | El PDF a analizar. Máx. 20MB. |
| `name` | string | Sí | Nombre del diseño nuevo. |
| `labelShapeId` | id | No | Forma de etiqueta a asociar (como en el alta normal). |

Respuesta: el `ProductPdfDesign` recién creado (mismo formato que `POST /product-pdf-designs`), con un mensaje indicando cuántos elementos se detectaron.

```json
{
  "success": true,
  "message": "Diseño importado del PDF (18 elementos detectados)",
  "data": { "id": 12, "name": "...", "data": { "pages": [ ... ] }, "is_published": false, ... }
}
```

Errores:
- `422` con los errores de validación si falta `pdf`/`name` o el archivo no es un PDF.
- `422` `"No se detectó ningún elemento en el PDF..."` si el análisis no encontró ni un fondo ni un texto — normalmente señal de que el PDF no vino de este sistema.
- `500` `"No se pudo analizar el PDF: ..."` si el archivo está corrupto o tiene un formato que el parser no reconoce.

El diseño se crea siempre **sin publicar** (`is_published: false`) y sin productos vinculados — queda para revisar y completar a mano en el editor antes de usarlo.

---

## Qué hace y qué no hace

Analiza el **contenido visual ya renderizado** del PDF (las operaciones de dibujo/texto reales), no el HTML ni el JSON del diseño original — por eso solo funciona con PDFs que efectivamente salieron de este backend (dompdf, sin compresión de fuentes rara ni cifrado).

Para cada "celda" de etiqueta detectada en la hoja genera, por separado:
- Un elemento `background` (color de fondo y borde, si lo detecta).
- Un elemento `icon` si detecta algo adentro de la celda que no sea texto ni el fondo mismo — tanto una imagen embebida (`Do`) como un ícono dibujado como SVG/trazos vectoriales (el caso más común en estos PDFs legacy, ver abajo). Queda con `icon_id: 1` (placeholder fijo).
- Un elemento `text` por celda, uniendo todas las líneas de texto que caen adentro (p. ej. "ISADORA" + "MUCCIGA" en dos renglones se combinan en un solo texto `"ISADORA MUCCIGA"`, no en dos elementos sueltos).

Se guarda la **grilla completa**, etiqueta por etiqueta — si la hoja tiene 60 etiquetas repetidas, el diseño queda con 60 juegos de fondo/ícono/texto, no un único template reutilizable. Es intencional: así el resultado es editable celda por celda igual que cualquier diseño armado a mano.

Aun así, esas 60 celdas no quedan sueltas: los 2-3 elementos de cada celda comparten un `group_id` (clickear/mover cualquiera selecciona/mueve la celda entera), y las celdas que comparten tamaño y forma se detectan como una grilla y se guardan en `data.pages[].grids[]` con el mismo formato que usa "Generar grilla" en el editor — se puede abrir esa herramienta después de importar para cambiar cols/filas/separación de todas esas celdas de una, en vez de reacomodarlas a mano una por una. Ver el detalle en [PDF_IMPORTAR_DESDE_PRODUCTO.md](PDF_IMPORTAR_DESDE_PRODUCTO.md#las-celdas-no-quedan-sueltas-agrupadas-y-con-generar-grilla-ya-armado).

Lo que **no** intenta hacer:
- **No matchea íconos ni tipografías contra los catálogos.** No hay forma confiable de reconocer qué ícono/fuente del catálogo corresponde a una imagen/letra ya rasterizada (o a un grupo de trazos vectoriales) dentro de un PDF, así que:
  - Todo ícono detectado queda con `icon_id: 1` (un placeholder fijo, solo para que la celda no se vea vacía) — hay que reemplazarlo por el ícono real en el editor.
  - Todo texto queda con `font_id: null` — se asigna a mano después en el editor.
- **No infiere campos dinámicos.** Todo texto se importa como `value_mode: "fixed"` con el contenido literal que tenía el PDF (p. ej. el nombre real de un cliente de ese PDF de ejemplo) — si en realidad correspondía a `nombre_apellido` o similar, hay que cambiarlo a mano en el editor.
- Texto que **no cae dentro de ninguna celda** (por ejemplo una leyenda suelta tipo "PEDIDO # 12345" al costado de la hoja, o una celda repetida que no tiene fondo propio — ver más abajo) se preserva igual como elemento suelto en la página, fuera de cualquier fondo — **respetando su rotación real** si la vista lo dibuja girado (ver más abajo), no solo su posición.
- Algunas vistas legacy (ej. `stars/*`, `spot-and-super-mini/PERSONALIZABLE`) repiten una celda (ícono + texto) MUCHAS veces sin pintarle un fondo de color — sin un rect que las delimite, cada ícono+texto de esas celdas se trata como texto suelto. Para no terminar mezclando el texto de celdas distintas en un solo bloque gigante (podían compartir casi la misma altura si están en la misma fila), el agrupado de texto suelto exige que estén cerca EN SIMULTÁNEO en X y en Y — una celda vecina en la misma fila está mucho más lejos en X que un salto de línea dentro de la misma celda, así que no se confunden aunque compartan la fila.

---

## Cómo detecta cada cosa

1. Lee el tamaño de página real del PDF (`MediaBox`) y arma la hoja del diseño con ese ancho/alto en cm.
2. Interpreta el *content stream* del PDF (las operaciones de dibujo bajas: rectángulos, texto, imágenes) para obtener posiciones y colores exactos — no mira una imagen rasterizada ni adivina por OCR.
3. Un rectángulo relleno que cubre ≥80% del área de la página se toma como **fondo de toda la hoja** (`sheet.background_color`), no como un elemento de etiqueta.
4. Del resto de los rectángulos, se queda solo con el "de afuera" en cada posición de la grilla (el fondo real de la celda) — cualquier rectángulo anidado DENTRO de otro se agrupa aparte como candidato a ícono, en vez de quedar como un fondo propio. Esto es necesario porque el ícono de estos PDFs legacy casi nunca es una imagen embebida: es un SVG que dompdf convierte a docenas de trazos vectoriales sueltos (muchos rectángulos chiquitos), no una sola imagen.
5. Para cada texto/imagen/grupo de trazos se busca la celda (rectángulo "de afuera") más chica que lo contiene, y a esa celda se le asigna el elemento. Si una celda tiene tanto una imagen embebida como trazos vectoriales sueltos, se usa la imagen (da una caja más confiable).
6. El elemento `icon` resultante usa la caja que ENGLOBA todos los trazos/imagen detectados en esa celda.
7. Para el texto de cada celda, el tamaño/posición del elemento `text` resultante usa el propio rectángulo contenedor (con un margen chico según el tamaño de fuente), no una estimación a partir de las letras — da resultados más estables que tratar de medir el texto.
8. **El interlineado (`line_height`) se mide directo de los renglones reales del PDF de origen** (la distancia entre uno y otro, dividida por el tamaño de fuente) — no hace falta reconstruir la regla CSS `line-height` de la vista legacy. El backend corrige el interlineado guardado por las métricas de la fuente que termine usando el elemento (ver `EtiquetaService::resolverElementoDesign`, `$fontRatio`) precisamente para que ese número reproduzca siempre el mismo espacio visual sin importar la fuente — así que el valor medido acá es, directamente, el que hay que guardar. Es una medición, no un valor exacto: en casos puntuales (ej. la primera celda de una fila) puede salir con una variación chica respecto a las demás celdas idénticas, por cómo cachea dompdf las métricas de fuente — sigue siendo mucho más preciso que el valor por defecto (1.15) que se usaba antes para todo.
8. **Texto suelto girado** (ej. "PEDIDO # 12345", dibujado en vertical al costado/abajo de la hoja en varias temáticas — ver `.numeroOrder p { transform: rotate(270deg) }` en `public/css/etiquetas.css`): el parser calcula el ángulo real del texto a partir de la matriz de transformación del PDF (`Tm`×`cm`), y el importador arma el elemento con `rotation_deg` seteado en vez de tratarlo como si fuera horizontal — antes quedaba ubicado mal (podía terminar debajo de toda la hoja, ya que el punto de "origen" de un texto girado no tiene sentido leído como si fuera horizontal). Sigue siendo una caja ESTIMADA (mismo criterio aproximado que el resto del texto suelto, por ancho de caracteres), así que puede no caer exacto — pero ahora cae en el costado/zona correcta de la hoja, con la rotación correcta, para ajustarla a mano desde ahí.

---

## Ejemplo de uso

```bash
curl -X POST https://tu-dominio/api/product-pdf-designs/import-from-pdf \
  -H "Authorization: Bearer {token}" \
  -F "pdf=@93130-combo-jardin.pdf" \
  -F "name=Combo jardín (importado)"
```

Después de importar, conviene abrir el diseño en el editor y:
1. Asignar los íconos reales del catálogo donde corresponda (quedaron en `icon_id: 1`, un placeholder).
2. Asignar tipografías donde corresponda (quedaron en `font_id: null`).
3. Revisar si algún texto en realidad debería ser dinámico (`nombre`, `nombre_apellido`, `fecha`, etc. — ver [PDF_FECHA_NUMERO_PEDIDO.md](PDF_FECHA_NUMERO_PEDIDO.md)) en vez de texto fijo.
4. Vincular el diseño (o páginas específicas) a los productos/variantes correspondientes.
