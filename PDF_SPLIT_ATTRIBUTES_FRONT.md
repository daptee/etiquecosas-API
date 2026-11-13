# Front: nuevo campo `accumulationSplitAttributes` en el editor de diseños acumulables

Contexto para quien no haya leído `PDF_DISENOS_ACUMULABLES.md`: un diseño "acumulable" es un PDF del día que se va completando con cada pedido (ej. bandas). Ya existe en el editor una sección "Agrupar por atributo" que separa los pedidos del día en páginas distintas según un atributo de la variante.

Caso nuevo que el backend ya soporta: productos de **varias unidades por pedido, cada una con su propio color** (ej. "Bandas de silicona personalizadas. 2 unidades" — cada variante es una combinación de 2 colores, vía los atributos de catálogo `"Color banda 1"`/`"Color banda 2"`). Para esto, el backend acepta un campo nuevo en el diseño: `accumulationSplitAttributes`, una lista ordenada de nombres de atributo (uno por cada unidad física del pack). Cuando está configurado, cada pedido genera una etiqueta por cada atributo de la lista (si las dos unidades son del mismo color, salen dos etiquetas iguales en ese color, a propósito — no se deduplican).

Sin este campo (como hasta ahora), todo sigue funcionando igual — es 100% opcional y no afecta a ningún diseño existente.

## Qué hay que agregar

**1. `components/productPdfDesigns/editor/AccumulationConfig.vue`**

Copiar el patrón de la sección "Agrupar por atributo" (ya existe en este archivo, usa `accumulationGroupAttribute` + el combobox de `attributeNames`), pero:
- Multi-select / combobox de **chips** (varios valores, no uno solo) en vez de un combobox simple.
- Mismos `items` (`attributeNames`, ya calculado en este componente).
- `model-value` → `editor.design.value.accumulationSplitAttributes` (array de strings, puede venir `null`/vacío).
- En el handler, llamar `editor.onUpdateAccumulation({ accumulationSplitAttributes: names })` (`names` = array de strings, `[]` o `null` si no queda ninguno elegido).
- Texto de ayuda sugerido: "Un atributo por cada unidad física del pedido (ej. 'Color banda 1', 'Color banda 2'): cada uno sale como una etiqueta aparte, con su propio color. Si dos unidades son del mismo color, salen dos etiquetas iguales."
- Ubicación sugerida: justo debajo de la sección "Agrupar por atributo", antes de "Cantidad por pedido".

**2. `types/productPdfDesigns/IProductPdfDesign.ts`**

Agregar, junto a `accumulationGroupAttribute: string | null;`:

```ts
accumulationSplitAttributes: string[] | null;
```

**3. `services/productPdfDesigns/productPdfDesigns.service.ts`**

Junto al mapeo existente (alrededor de la línea 108):

```ts
accumulationGroupAttribute:
  raw.accumulation_group_attribute ?? raw.accumulationGroupAttribute ?? undefined,
```

agregar:

```ts
accumulationSplitAttributes:
  raw.accumulation_split_attributes ?? raw.accumulationSplitAttributes ?? undefined,
```

**4. `utils/PdfAccumulationHelper.ts`**

Agregar el default en `ACCUMULATION_DEFAULTS` (ej. `accumulationSplitAttributes: null` o `[]`, a definir según cómo maneje el resto del archivo arrays vacíos vs. `null`) y sumarlo a la misma normalización que ya existe para `accumulationGroupAttribute`, para que quede consistente al guardar.

## Backend — ya está listo

- `POST /product-pdf-designs/{id}` ya acepta `accumulationSplitAttributes` (array de strings) y lo valida/guarda.
- Falta correr, en cada entorno, `database/sql/alter_product_pdf_designs_add_split_attributes.sql` (agrega la columna) — avisen cuando lo necesiten y lo coordinamos.
- Ejemplo de request ya funcional (se puede probar con Postman/curl mientras no está la UI):

```json
POST /api/product-pdf-designs/42
{
  "name": "Bandas x2",
  "fillMode": "accumulating",
  "accumulationGroupAttribute": "Color",
  "accumulationSplitAttributes": ["Color banda 1", "Color banda 2"]
}
```

Más contexto del feature completo (cómo arma las páginas, cómo resuelve el color real de cada atributo, etc.) en `PDF_DISENOS_ACUMULABLES.md`, sección 1.2.
