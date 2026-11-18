# Directorios de PDF — Buscar por nombre de PDF

Se agregó el query param opcional **`search`** a los dos listados de `pdf-directories`.

- Coincidencia **parcial** (contiene) y **sin distinguir mayúsculas/minúsculas**.
- Si `search` no se envía o va vacío, los endpoints funcionan igual que antes.
- Se combina con los filtros que ya existían (`start_date`, `end_date`, `user_id`, `page`, `per_page`).
- Requiere el mismo auth de siempre (`jwt.auth`), y se siguen aplicando los permisos por perfil: un diseñador solo ve sus propios PDFs.

---

## 1. Listado de carpetas

`GET /api/pdf-directories`

### Query params

| Param        | Tipo   | Requerido | Descripción                                             |
|--------------|--------|-----------|---------------------------------------------------------|
| `search`     | string | no        | **Nuevo.** Texto a buscar en el nombre del PDF          |
| `start_date` | string | no        | Fecha desde (`YYYY-MM-DD`)                              |
| `end_date`   | string | no        | Fecha hasta (`YYYY-MM-DD`)                              |
| `user_id`    | int    | no        | Solo admin: filtrar por diseñador                       |
| `page`       | int    | no        | Página (default `1`)                                    |
| `per_page`   | int    | no        | Ítems por página (default `15`)                         |

### Comportamiento con `search`

- Solo se devuelven las carpetas (fechas) que tienen **al menos un PDF de pedido** cuyo nombre coincida.
- `total_pdfs` pasa a ser la **cantidad de PDFs que coinciden** en esa carpeta (no el total de la carpeta).
- La paginación (`metaData.total`, `metaData.last_page`, etc.) se calcula sobre las carpetas filtradas.
- ⚠️ En este listado la búsqueda mira solo los **PDFs de pedidos** (`planchas`). Los extras (cintas coser/planchar, bandas, sellos, acumulables) no se tienen en cuenta acá. Si una fecha solo tiene un extra que coincide, esa carpeta no aparece.

### Ejemplo

```
GET /api/pdf-directories?search=1234&start_date=2026-11-01&page=1&per_page=15
```

```json
{
  "message": "Carpetas de PDF obtenidas exitosamente",
  "data": [
    {
      "fecha": "17-11-2026",
      "fecha_ordenable": "2026-11-17",
      "total_pdfs": 2,
      "ruta": "..."
    }
  ],
  "metaData": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1,
    "from": 1,
    "to": 1
  }
}
```

---

## 2. PDFs de una fecha

`GET /api/pdf-directories/{fecha}` — `fecha` en formato `dd-mm-yyyy`

### Query params

| Param     | Tipo   | Requerido | Descripción                                    |
|-----------|--------|-----------|------------------------------------------------|
| `search`  | string | no        | **Nuevo.** Texto a buscar en el nombre del PDF |
| `user_id` | int    | no        | Solo admin: filtrar por diseñador              |

### Comportamiento con `search`

- Filtra **ambas** listas: `pdfs_pedidos` y `extras`.
- En los extras de **diseños acumulables**, además del nombre del archivo (`{design_id}-{fecha}.pdf`), también busca en `design_name`. Por ejemplo, buscar `"Escolar"` encuentra el acumulable del diseño "Etiquetas Escolares".
- Si nada coincide, las dos listas vuelven vacías (`[]`). La respuesta sigue siendo `200`.

### Ejemplo

```
GET /api/pdf-directories/17-11-2026?search=coser
```

```json
{
  "message": "PDFs obtenidos exitosamente",
  "data": {
    "fecha": "17-11-2026",
    "pdfs_pedidos": [],
    "extras": [
      {
        "nombre": "17-11-2026-coser-x24.pdf",
        "ruta": "...",
        "tamanio": 123456,
        "fecha_modificacion": "2026-11-17 10:32:11"
      }
    ]
  },
  "metaData": null
}
```

---

## Sugerencias para el front

- Usar **un solo input de búsqueda** con *debounce* (~300–400 ms) antes de llamar al endpoint.
- Al cambiar `search` en el listado de carpetas, **volver a `page=1`**.
- Al entrar a una carpeta desde una búsqueda, **pasar el mismo `search`** a `GET /pdf-directories/{fecha}` para que se vean directamente los PDFs que coinciden. El badge de `total_pdfs` va a coincidir con lo que se muestra.
- Mostrar un estado vacío del tipo *"No se encontraron PDFs con «{search}»"* cuando `data` venga vacío.
- Enviar el valor con `encodeURIComponent` (los nombres pueden tener espacios o guiones).

## Fuera de alcance

- `GET /pdf-directories/{fecha}/download-zip` **no** aplica el filtro: siempre descarga la carpeta completa.
