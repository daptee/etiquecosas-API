# Mostrar íconos personalizados en el preview del editor

`GET /product-pdf-designs/{id}/preview` genera el PDF con el mismo motor que una venta real, pero sin una venta real detrás — así que, hasta ahora, cualquier ícono que dependiera de la personalización del cliente salía **en blanco** (a menos que el elemento tuviera un `icon_id` de respaldo). Ahora el preview acepta parámetros para simular esa personalización.

---

## Parámetros nuevos (query string, todos opcionales)

| Parámetro | Para qué elemento | Ejemplo |
|---|---|---|
| `icon` | Elementos con `editable_by_customer: true, editable_field: "icon"` (el ícono "libre" que el cliente elige en el checkout) | `icon=icons/personalization/icon_69022e76980ef.svg` |
| `variantId` | Elementos con `dynamic_attribute_id` (íconos que vienen de un atributo de la variante, ej. "Iconos", "Color banda 2") | `variantId=3067` |
| `color` | Elementos con `editable_field: "color"` | `color=%23FF0000` |

Ya existían `firstName`/`lastName` para el nombre — siguen igual.

---

## Ejemplos

**Ícono libre** (el mismo mecanismo que `customization_data.icon.icon` en una venta real):

```
GET /product-pdf-designs/6/preview?firstName=JUAN&lastName=PEREZ&icon=icons/personalization/icon_69022e76980ef.svg
```

**Ícono por atributo** (`dynamic_attribute_id`): hace falta el `id` de una **variante real** (`product_variants.id`) que tenga cargado ese atributo, no el id del producto ni de la temática:

```
GET /product-pdf-designs/6/preview?firstName=JUAN&lastName=PEREZ&variantId=3067
```

Con esto, cada elemento que tenga `dynamic_attribute_id` busca, en `attribute_values` de esa variante puntual, el ícono correspondiente a ese atributo — igual que en una venta real.

**Los dos juntos** (un diseño puede tener ambos tipos de ícono a la vez):

```
GET /product-pdf-designs/6/preview?firstName=JUAN&lastName=PEREZ&icon=icons/personalization/icon_69022e76980ef.svg&variantId=3067
```

---

## Si no se manda nada

Sin `icon` ni `variantId`, el preview se comporta exactamente como antes: los elementos con `icon_id` fijo se ven, y los que dependen de personalización (`editable_field:"icon"` o `dynamic_attribute_id`) salen en blanco — no rompe nada de lo que ya funcionaba.

## De dónde sacar el `variantId`

Es el `id` de `product_variants` — el mismo que ya se usa como `theme_key` al vincular el diseño a un producto (ver [PDF_DESIGNS_BULK_LINKS.md](PDF_DESIGNS_BULK_LINKS.md)), y el mismo `variant_id` que trae cada línea de una venta real.
