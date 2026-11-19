# Importar un diseño PDF: íconos que no existen en el destino

Complementa [PDF_EXPORTAR_IMPORTAR_DISENO.md](PDF_EXPORTAR_IMPORTAR_DISENO.md).

Al importar un diseño, cada ícono se busca **por nombre** en el destino. Hasta ahora, uno que no existía se
creaba solo con el SVG de la exportación, y eso sigue siendo lo que pasa por defecto. Ahora el front puede
preguntarle al usuario qué hacer con cada uno:

- **crearlo** con el SVG de la exportación;
- **usar un ícono que ya existe** en el catálogo;
- **subir un SVG** en ese momento.

## Campos nuevos en `POST /api/product-pdf-designs/import`

| Campo | Tipo | |
|---|---|---|
| `createMissingIcons` | bool, opcional (default `true`) | `false`: no crear solos los íconos que no existen. Si falta alguno sin decidir, responde `422` con la lista. |
| `iconActions[<sourceIconId>]` | objeto, opcional | Qué hacer con ese ícono. La clave es el **id del ícono en el entorno de origen**, el que viene en `missingIcons`. |

El resto de los campos (`file`, `bundle`, `name`, `targetDesignId`) no cambia.

## Flujo

### 1. Mandar el import con `createMissingIcons=false`

Si falta algún ícono sin decidir, **no se importa nada** y la respuesta es `422`:

```json
{
  "status": 422,
  "message": "Hay íconos que no existen en este entorno: elegí qué hacer con cada uno.",
  "missingIcons": [
    { "sourceIconId": 373, "name": "Perrito pelota", "file": "data:image/svg+xml;base64,PHN2Zy..." }
  ]
}
```

- `file` es el SVG que trae la exportación, listo para un `<img :src="file">`. Es `null` si no vino.
- Si no falta ningún ícono, importa directamente y responde `200`, igual que siempre.

Este `422` trae `missingIcons` y no trae `errors`. Así se distingue de un error de validación.

### 2. Mostrar un diálogo

Una fila por cada ícono faltante, con su SVG, su nombre y tres opciones:

| Opción | Qué mandar en `iconActions[<sourceIconId>]` |
|---|---|
| Crearlo con el SVG de la exportación | `action=create` |
| Usar un ícono que ya existe (elegido del catálogo, `GET /api/icons`) | `action=use`, `iconId=<id del ícono en el destino>` |
| Subir un SVG ahora | `action=upload`, `file=<archivo .svg, hasta 2 MB>` y, opcional, `name` (si no viene, el nombre original) |

### 3. Volver a mandar el import

El mismo archivo, con `createMissingIcons=false` y las `iconActions`. En multipart/form-data:

```
file                         = <el .json>
createMissingIcons           = false
iconActions[373][action]     = create
iconActions[374][action]     = use
iconActions[374][iconId]     = 12
iconActions[375][action]     = upload
iconActions[375][file]       = <perrito.svg>
iconActions[375][name]       = Perrito pelota
```

Con `name`, `targetDesignId`, etc., igual que en el primer envío.

## Respuesta

Es la misma respuesta de siempre (`data.design` y `data.report`). Cada decisión aparece en el `report`:

- `matched`: `Ícono "Perrito pelota" → "Ancla" (#1, elegido)`;
- `created`: `Ícono "Perrito pelota" (#380, archivo subido)`;
- `created`: `Ícono "Perrito pelota" (#381)` (creado con el SVG de la exportación).

## A tener en cuenta

- `upload` funciona solo en **multipart**, con `file`. Si el import se manda con `bundle` en JSON, se pueden usar
  `create` y `use`.
- `iconActions` también funciona con `createMissingIcons=true`. Sirve, por ejemplo, para usar otro ícono del
  destino en lugar del que tiene el mismo nombre.
- Si el nombre de un SVG subido ya existe en el catálogo, se le agrega " (2)", " (3)", etc.
- Cada elemento del diseño importado queda con el `icon_id` del destino y su `icon_name`.
- Si el import falla, no queda nada creado: ni el diseño, ni los íconos, ni los SVG subidos.

## Errores

| Respuesta | Causa |
|---|---|
| `422` con `missingIcons` | Hay íconos sin decidir (solo con `createMissingIcons=false`). |
| `422` con `errors["iconActions.<id>.action"]` | Acción inválida: tiene que ser `create`, `use` o `upload`. |
| `422` con `errors["iconActions.<id>.iconId"]` | `use` sin `iconId`, o ese ícono no existe. |
| `422` con `errors["iconActions.<id>.file"]` | `upload` sin archivo, o el archivo no es SVG o pasa de 2 MB. |
