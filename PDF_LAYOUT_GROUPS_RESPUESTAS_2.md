# Respuesta del backend (2): `layout_groups`

Respuesta a "decisiones, pedidos y preguntas" del frontend. Las decisiones de la sección 1 quedan aceptadas tal cual, sin cambios del lado del backend.

## Antes de empezar: un bug en el corte de líneas

Al revisar `dividirEnRenglones` para pasarles el algoritmo, apareció un bug que afecta la altura:

> **Si la primera palabra tiene `max_chars_per_line` caracteres o más, y `max_lines` ≥ 2, se genera una línea vacía arriba.**

| Texto | `max_lines` / `max_chars_per_line` | Resultado hoy |
|---|---|---|
| `GUILLERMINA` | 3 / 10 | `""`, `GUILLERMINA` (2 líneas, la primera vacía) |
| `ABCDEFGHIJ` (justo 10) | 3 / 10 | `""`, `ABCDEFGHIJ` |
| `ROBERTITO` (9) | 3 / 10 | `ROBERTITO` |

La causa es que, con la línea actual vacía, se mide `" " + palabra` (cuenta un espacio de más) y se "cierra" una línea vacía. Hoy, con `vertical_align: middle`, eso deja el texto medio renglón más abajo de lo esperado.

**Propuesta:** corregirlo en el backend junto con la fase 1 (afecta también a los diseños actuales que caen en este caso, para mejor) y que el frontend replique la versión corregida. Abajo, en 2.3, va el algoritmo ya corregido. Si prefieren no tocar diseños existentes, avisen y lo corregimos solo dentro de los grupos.

---

## 2. Lo que pidieron del backend

### 2.1 Render de un texto dentro de un grupo (fase 1)

Confirmado, con estos detalles exactos:

- **Caja del elemento:** `left = x calculado`, `top = y calculado`, `width = width_cm` (sin cambios en fase 1), `height = H`.
- **Alto:** `H = líneas × font_size_px × line_height × 2,54 / 96` (en cm).
  - `font_size_px`, `line_height` y `letter_spacing_px` son los ya resueltos por sus reglas por longitud.
  - `line_height` es el número del diseño. La corrección interna por métricas de la fuente es transparente y no cambia la fórmula.
- **Contenido:** `top: 0`, sin `transform`, sin `vertical_align` ni ajustes ópticos.
- **`white-space: nowrap`:** las líneas son exactamente las del corte por caracteres; no hay un segundo corte por ancho. Si una línea es más ancha que `width_cm`, desborda hacia los costados.
- **`text_align`:** sigue aplicando dentro del `width_cm` de la caja.
- **`min_lines`:** no se completan líneas.
- **Dentro de cada renglón**, el texto se ubica como en CSS estándar (medio interlineado arriba y abajo). Del lado del editor, CSS normal. Si dompdf ubica los glifos unas décimas de mm distinto, lo compensa el backend con un ajuste interno; se verá en los casos de prueba.

### 2.2 Tokens en textos fijos

**Recomiendo el camino A.** Costo: bajo, alrededor de 1 hora. Es agregar los tokens al reemplazo que ya existe en `resolverElementoDesign`.

- **`{{fecha}}`:** mismo valor que `dynamic_field: "fecha"`. Formato `d/m/Y` (ej. `30/09/2026`), zona horaria de Buenos Aires. Es la fecha en que se aprueba el pago (cuando se generan los PDF), no la de creación de la venta.
- **`{{numero_pedido}}`:** mismo valor que `dynamic_field: "numero_pedido"`: el `sales.id`.
- **`{{id_producto}}`:** **hay que definirlo**, porque hoy no existe nada equivalente. Opciones:
  - `products.id` del producto comprado (mi propuesta por defecto);
  - el id de la variante (`product_variants.id`);
  - el SKU.

  ¿Para qué se usa en la etiqueta? Con eso elegimos. En el preview no hay venta real: se muestra `0`, o lo que llegue por un parámetro nuevo `?idProducto=`.

Las reglas por longitud se evalúan sobre el texto ya sustituido, igual que hoy.

### 2.3 Algoritmo de corte de líneas

Es la función `dividirEnRenglones(texto, maxLines, maxChars, firstName)` de [app/Services/EtiquetaService.php](app/Services/EtiquetaService.php). Valores por defecto: `maxLines = 3`, `maxChars = 10`. Va en pseudocódigo, **con el bug ya corregido** (marcado ★).

```text
texto = trim(texto)                  // trim de PHP: espacios, \t, \n, \r, \0, \x0B en los extremos

// --- Caso nombre completo ---
// firstName solo se pasa si el texto es dynamic_field "nombre_apellido"
// o si content contiene {{customer_name}}. Es el nombre tal cual lo cargó el cliente.
si firstName no es null ni "":
    nombre = trim(firstName)
    si largo(texto) <= maxChars:
        devolver [texto]                                    // 1 línea
    apellido = trim(subcadena(texto, desde = largo(nombre)))   // corta por LARGO, no busca el nombre
    si apellido != "":
        lineas = [nombre, apellido]                         // el apellido no se vuelve a cortar
        si maxLines == 1:
            lineas = [nombre + "…"]                         // se pierde el apellido
        devolver lineas
    // si apellido quedó vacío, sigue al caso general

// --- Caso general ---
palabras = separar(texto, " ")       // por UN espacio: "Ana  Paz" da ["Ana", "", "Paz"]
tokens = agruparParticulas(palabras)

lineas = []
actual = ""
para cada token:
    candidato = (actual == "") ? token : actual + " " + token     // ★ antes: siempre actual + " " + token
    si largo(candidato) > maxChars y cantidad(lineas) < maxLines - 1 y actual != "":   // ★ "y actual != ''"
        lineas.agregar(trim(actual))
        actual = token
    si no:
        actual = candidato
lineas.agregar(trim(actual))
devolver lineas                      // nunca más de maxLines: lo que sobra queda pegado en la última
```

```text
agruparParticulas(palabras):
    PARTICULAS = DE, DEL, DE LA, DE LOS, DE LAS, DI, LA, LAS, LOS, EL, Y, VAN, VON, BIN, BTE
    // se comparan en MAYÚSCULAS (mb_strtoupper); el texto conserva cómo se escribió
    i = 0
    mientras i < n:
        si i+2 < n y mayus(palabras[i] + " " + palabras[i+1]) está en PARTICULAS:
            agregar palabras[i] + " " + palabras[i+1] + " " + palabras[i+2];  i += 3
        si no, si i+1 < n y mayus(palabras[i]) está en PARTICULAS:
            agregar palabras[i] + " " + palabras[i+1];  i += 2
        si no:
            agregar palabras[i];  i += 1
```

`largo` = cantidad de caracteres Unicode (`mb_strlen`); "Á" cuenta 1.

**Casos que preguntaron:**

| Caso | Comportamiento |
|---|---|
| Sobra texto respecto de `max_lines` | Se pega en la última línea. Ej.: `uno dos tres cuatro cinco seis siete` con 3 / 8 queda `uno dos`, `tres`, `cuatro cinco seis siete` |
| Palabra más larga que `max_chars_per_line` | No se parte; queda sola en su línea |
| Partículas | Pueden dejar una línea más larga que `max_chars_per_line`: `María de los Ángeles González` con 3 / 10 queda `María`, `de los Ángeles` (14), `González` |
| Espacios dobles | Generan un token vacío, que cuenta en el largo. `Ana  Paz` queda en una línea, contado como 8 caracteres. Al dibujar, el HTML lo muestra con un solo espacio |
| Saltos de línea (`\n`) internos | **No** cortan línea: cuentan como un carácter más y al dibujar se ven como un espacio. Solo se eliminan en los extremos |
| Texto solo con espacios | Queda `""`: miembro ausente dentro de un grupo |
| Nombre completo con texto alrededor (`Hola {{customer_name}}`) | El corte nombre/apellido parte por el largo del nombre desde el principio, así que sale mal (`Hola`, `Ana Paz` en vez de usar el nombre). Recomendación: `{{customer_name}}` solo, sin texto alrededor |

**Para las reglas por longitud** se cuenta `mb_strlen` del texto sustituido **sin** `trim`: los espacios cuentan, incluidos los de los extremos.

**De paso:** el texto final hoy se inserta en el HTML sin escapar. Un nombre con `<` o `&` puede romperse. Lo vamos a escapar en la fase 1; del lado del frontend no cambia nada.

### 2.4 Fuentes

- **Fuente predeterminada compatible con Arial:** sí, se puede. Se agrega `LiberationSans-Regular.ttf` (licencia SIL OFL, mismas métricas que Arial) a `public/fonts`, se declara con `@font-face` en el render y se usa como respaldo en todos los textos: `font-family: '<fuente del elemento>', 'Liberation Sans'`.
  - `font_id` vacío pasa a salir con Liberation Sans, que coincide con el Arial del editor.
  - Una fuente subida que no carga **debería** caer en Liberation Sans en vez de Times. Hay que verificar que dompdf respete la lista de respaldo cuando falla un `@font-face`; lo confirmamos en las pruebas.
  - Costo: medio día con pruebas.
- **Caché de fuentes:** desde el repo no puedo verificar producción. Hay que revisar en el servidor que `storage/fonts_cache` exista, tenga permisos de escritura y contenga archivos `*.ufm.json`. En cualquier caso, propongo **unificar** usando la ruta de la configuración (`storage/fonts`) y sacar el cambio en tiempo de ejecución, así deja de haber dos lugares.

---

## 3. Preguntas abiertas

**1. Posición dentro del contenedor.** Sí, exactamente eso. En `vertical`:
- el ancho del bloque del grupo es el del miembro más ancho;
- el bloque se centra horizontalmente en la etiqueta;
- cada miembro se alinea dentro del bloque según `align` (`start` = borde izquierdo del bloque, `center`, `end` = borde derecho).

En la fase 1, el ancho de un texto es el `width_cm` de su caja (no se mide el texto). Por eso conviene que las cajas de texto tengan el ancho de la etiqueta y usar `text_align` para alinear el texto adentro.

**2. Un solo miembro presente.** Se centra solo, sin `gap`. El `gap` va únicamente entre miembros presentes: con *k* presentes, total = suma de tamaños + `gap_cm` × (*k* − 1). Con cero presentes no se hace nada (los ausentes no dibujan nada).

**3. Rotación.** Si llega `rotation_deg` en un miembro, se ignora para el cálculo y además se dibuja sin rotación. Así el PDF coincide con lo que calcula el editor.

**4. Desborde.** Sí, se registra un `warning` en el log con diseño, página, grupo y cuánto desborda. No cambia el PDF.

**5. Validación.** Las dos cosas, según el momento:

- **Al guardar** (`POST /product-pdf-designs` y `/{id}`): **422** con mensaje claro, en `data.pages.{i}.layout_groups.{j}`, cuando:
  - falta `id`, o hay `id` de grupo repetido en la página;
  - `container_element_id` no existe en la página o no es de tipo `background`;
  - `members` está vacío, o tiene un id que no existe en la página o que no es `text` ni `icon`;
  - un mismo elemento está en dos grupos;
  - `direction` o `align` traen un valor desconocido, o `gap_cm` no es un número entre 0 y 50.
- **Al generar el PDF** (datos viejos, o algo que cambió después de guardar): se descarta en silencio con un `warning` en el log. Si se repite un miembro, gana el primer grupo.

**6. Fase 2: ancho de un texto con varias líneas.** Se usa la línea más ancha: `max(getTextWidth(línea))` medido con la fuente, el tamaño y el letter-spacing resueltos, con tope en `width_cm`. El `width_cm` del texto se fija a ese valor, y el `align` del grupo ubica esa caja.

**7. Estimación.** Confirmo los plazos para el núcleo: fase 1, 1 a 2 días; fase 2, 2 a 3 días. Lo que sumamos acá agrega alrededor de **1 día** a la fase 1:
- corrección del bug de línea vacía;
- escapado del texto;
- tokens del camino A;
- Liberation Sans;
- validación 422.

Lo que podría moverlos:
- la ubicación vertical de los glifos en dompdf (que haya que ajustar contra los casos de prueba);
- que la caché de fuentes de producción esté mal;
- que dompdf no respete la lista de respaldo de fuentes;
- el ida y vuelta de comparar PDF contra editor.

---

## 4. Casos de prueba

Los PDF y las posiciones se pueden generar recién cuando esté la fase 1. Propuesta para que la comparación sea rápida:

- **Endpoint de comparación:** agregamos `GET /product-pdf-designs/{id}/preview?format=layout`, que en vez del PDF devuelve un JSON con `x/y/width/height` en cm de cada miembro después del layout, más las líneas resultantes de cada texto. Sirve para estos casos y para depurar cualquier diferencia más adelante.
- **Diseño de prueba:** que el frontend arme en el admin un diseño de prueba con una página por caso (los 9). Así los ids y la estructura son los que realmente emite el editor. Nos pasan el id y devolvemos el PDF y el JSON de posiciones de cada página.

Mientras tanto, estos resultados de corte ya se pueden fijar (texto plano, 3 / 10, con el bug corregido):

| Texto | Líneas |
|---|---|
| `Ana` | `Ana` |
| `María Fernanda` | `María`, `Fernanda` |
| `María de los Ángeles González` | `María`, `de los Ángeles`, `González` |
| Caso 6: nombre completo `María Fernanda`, nombre `María`, 2 / 8 | `María`, `Fernanda` |
| Caso 7: `uno dos tres cuatro cinco seis siete`, 3 / 8 | `uno dos`, `tres`, `cuatro cinco seis siete` |

## Pendiente de respuesta del frontend

1. ¿Corregimos el bug de línea vacía para todos los diseños, o solo dentro de los grupos?
2. ¿Qué es `{{id_producto}}`: `products.id`, id de variante o SKU?
3. ¿Les sirve `?format=layout` y el diseño de prueba armado por ustedes?
