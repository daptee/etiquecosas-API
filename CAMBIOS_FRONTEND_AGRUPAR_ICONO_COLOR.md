# Cambios de front-end pendientes de portar: agrupar por "Ícono"/"Color"

Esto ya está aplicado en `storage/app/etiquecosas-admin-development` (el admin que corre en este repo), para que sirva de referencia — pero si el front-end real vive en otro repositorio, hay que portar estos 2 cambios ahí a mano.

Contexto: el combobox "Agrupar por atributo" del editor de diseños PDF (modo "Acumulando") solo ofrecía atributos de **variante de catálogo** (ej. "Cantidad"). Productos como Cinta Planchar/Coser no tienen variante de ícono/color — el cliente los elige como **personalización libre** de la compra (`customization_data`), un sistema totalmente distinto. El backend (`AccumulatingPdfDesignService::resolverValorDeAgrupacion()`) ya sabe agrupar por esa personalización cuando el valor guardado es exactamente `"Ícono"`/`"icono"`/`"icon"` o `"Color"` (sin distinguir mayúsculas). Falta que el combobox ofrezca esas dos opciones cuando el producto las tenga activas.

---

## 1. `composables/productPdfDesigns/useDesignEditor.ts`

### 1.a Extender la interfaz `ProductPersonalization`

```ts
// Which "de la compra" fields the associated product actually supports — drives
// ElementConfig's Campo select (only Nombre/Apellido, and only if enabled on the product).
export interface ProductPersonalization {
	isCustomizable: boolean;
	isNameActive: boolean;
	isLastNameActive: boolean;
	isIconActive: boolean;   // NUEVO
	isColorActive: boolean;  // NUEVO
}
```

### 1.b Calcular los 2 flags nuevos en `loadProductsContext`

Buscar el bloque que arma `productPersonalization.value` (dentro de `loadProductsContext`) y agregar las 2 líneas nuevas:

```ts
productPersonalization.value = products.length
	? {
			isCustomizable: products.some(p => Boolean(p.is_customizable)),
			isNameActive: products.some(p =>
				Boolean(p.customization?.customization?.is_name_active)
			),
			isLastNameActive: products.some(p =>
				Boolean(p.customization?.customization?.is_last_name_active)
			),
			isIconActive: products.some(p =>               // NUEVO
				Boolean(p.customization?.customization?.is_icons_active)
			),
			isColorActive: products.some(p =>               // NUEVO
				Boolean(p.customization?.customization?.is_colors_active)
			)
		}
	: null;
```

`is_icons_active`/`is_colors_active` ya vienen en la respuesta de producto (mismo objeto `p.customization.customization` de donde salen `is_name_active`/`is_last_name_active`), no hace falta ningún fetch nuevo.

No hace falta tocar el `return` del composable: `productPersonalization` ya se exporta desde ahí.

---

## 2. `components/productPdfDesigns/editor/AccumulationConfig.vue`

Reemplazar el computed `attributeNames` (el que alimenta el `v-combobox` de "Agrupar por atributo"):

```ts
// ANTES
const attributeNames = computed(() => [
	...new Set(editor.productAttributes.value.map(attribute => attribute.name))
]);
```

```ts
// DESPUÉS
// Ícono/Color del producto pueden venir de dos lugares distintos: un atributo de
// VARIANTE de catálogo (ya cubierto por editor.productAttributes), o un campo de
// PERSONALIZACIÓN libre de la compra (is_icons_active/is_colors_active, sin variante
// de catálogo detrás — ej. Cinta Planchar). Si el producto tiene la personalización
// activa, se ofrecen como opción adicional del combobox, aunque no sean un atributo
// real — el backend sabe agrupar por el ícono/color que cada pedido trajo
// (AccumulatingPdfDesignService::resolverValorDeAgrupacion()).
const attributeNames = computed(() => {
	const names = new Set(editor.productAttributes.value.map(attribute => attribute.name));
	const personalization = editor.productPersonalization.value;
	if (personalization?.isIconActive) names.add('Ícono');
	if (personalization?.isColorActive) names.add('Color');
	return [...names];
});
```

No hace falta tocar `setGroupAttribute` ni ningún otro handler: el `v-combobox` ya guarda el texto tal cual (free text + sugerencias), y el string `"Ícono"`/`"Color"` se manda sin cambios a `accumulationGroupAttribute`, que es justo lo que el backend ahora reconoce.

---

## Importante: el string tiene que coincidir con lo que espera el backend

El backend compara en minúsculas y acepta estas variantes exactas (ver `AccumulatingPdfDesignService::resolverValorDePersonalizacion()`):

| Concepto | Valores aceptados |
|---|---|
| Ícono | `"Ícono"`, `"icono"`, `"icon"` |
| Color | `"Color"` |

Si el combobox llega a ofrecer una etiqueta distinta (ej. traducciones, mayúsculas distintas no cubiertas), hay que ajustar también `resolverValorDePersonalizacion()` en `app/Services/AccumulatingPdfDesignService.php` para que la reconozca, o usar exactamente estos strings en el front.
