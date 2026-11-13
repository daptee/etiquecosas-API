# Front: tamaño del ícono según la longitud del texto agrupado

Contexto completo en `PDF_ESCALA_ICONO_POR_TEXTO.md` — el backend ya soporta que un ícono agrupado (mismo `group_id`) con un texto cambie su `icon_scale_pct` (el slider que ya existe en "Tamaño del ícono", 10-100%) según cuántos caracteres tiene ese texto, igual que ya existe "Tamaño por longitud (px)" para el tamaño de fuente del texto. Caso real: en una etiqueta de nombre + ícono (ej. `Etiquetas banda.json`), si el nombre es largo el ícono se achica para no pisarlo.

Sin esto, nada cambia — es 100% opcional.

## Qué hay que agregar

**1. `types/productPdfDesigns/IProductPdfDesign.ts`**

Junto a `PdfTextLengthRule`/`PdfTextFontSizeRuleWire` (línea ~95-107), agregar el mismo par pero para el ícono:

```ts
// A tier in an `icon` element's length-based auto-scale table: when this icon is grouped
// (same group_id) with a text element, and that text is up to `max_chars` characters long,
// the icon draws at `icon_scale_pct` (same field/range as the plain icon_scale_pct slider).
// Only applied when `icon_length_rules_enabled` is true. Has no effect on an icon that isn't
// grouped with any text — see PDF_ESCALA_ICONO_POR_TEXTO.md.
export interface PdfIconLengthRule {
	max_chars: number;
	icon_scale_pct: number;
}

// Wire shape of the same table for the backend's `icon_size_rules`: same tiers, last one's
// `max_chars` is `null` ("para el resto") instead of the editor's numeric sentinel — stamped
// from `icon_length_rules` at save time, see PdfDesignHelper.resolveIconSizeRulesPayload.
export interface PdfIconSizeRuleWire {
	max_chars: number | null;
	icon_scale_pct: number;
}
```

Y en `PdfDesignElement`, junto a `icon_scale_pct` (línea ~166):

```ts
// `icon` only: editable state of the "Tamaño por longitud" toggle (mirrors length_rules_enabled
// for text) — true means icon_scale_pct is picked from icon_length_rules by the grouped text's
// character count instead of using the fixed icon_scale_pct above.
icon_length_rules_enabled?: boolean; // default false
icon_length_rules?: PdfIconLengthRule[];
// Backend wire field for the table above — stamped from icon_length_rules_enabled/icon_length_rules
// at save time (PdfDesignHelper.resolveIconSizeRulesPayload). Never edited directly.
icon_size_rules?: PdfIconSizeRuleWire[];
```

**2. `components/productPdfDesigns/editor/ElementConfig.vue`**

En la sección `type === 'icon'`, debajo de "Tamaño del ícono" (línea ~371-391), agregar un bloque igual al de texto "Tamaño por longitud (px)" (línea ~712-773), pero para `icon_scale_pct`:

```html
<div class="ec-sect">
	<div class="ec-sect-title ec-sect-title-switch">
		<span>Tamaño por longitud del texto agrupado</span>
		<v-switch
			:model-value="!!element.icon_length_rules_enabled"
			hide-details
			inset
			density="compact"
			color="primary"
			@update:model-value="v => update({ icon_length_rules_enabled: !!v })"
		/>
	</div>
	<template v-if="element.icon_length_rules_enabled">
		<p class="ec-hint">
			Según la cantidad de caracteres del texto agrupado con este ícono (mismo Grupo), ajustá
			la escala para que no se superpongan. Si este ícono no está agrupado con ningún texto,
			esto no tiene efecto.
		</p>
		<div class="ec-rule-list">
			<div v-for="(rule, i) in iconLengthRules" :key="i" class="ec-rule-row">
				<div class="ec-rule-field">
					<span>Hasta</span>
					<input type="number" class="ec-rule-input" :value="rule.max_chars"
						@input="onIconRuleMaxCharsInput(i, $event, false)"
						@change="onIconRuleMaxCharsInput(i, $event, true)" />
					<span>car.</span>
				</div>
				<span class="ec-rule-arrow">→</span>
				<div class="ec-rule-field">
					<input type="number" class="ec-rule-input" min="10" max="100" :value="rule.icon_scale_pct"
						@input="onIconRuleScaleInput(i, $event, false)"
						@change="onIconRuleScaleInput(i, $event, true)" />
					<span>%</span>
				</div>
				<v-btn icon="mdi-close" size="x-small" variant="text" density="compact" @click="removeIconRule(i)" />
			</div>
		</div>
		<v-btn size="small" variant="outlined" block class="ec-field-spacing" @click="addIconRule">
			<v-icon start icon="mdi-plus" size="small" />
			Agregar regla
		</v-btn>
	</template>
	<p v-else class="ec-hint">Desactivado. El ícono usa la escala fija de arriba.</p>
</div>
```

Y en el `<script>`, el mismo patrón que `lengthRules`/`addRule`/`removeRule`/`onRuleMaxCharsInput`/`onRuleFontSizeInput` (buscarlos en este mismo archivo) pero leyendo/escribiendo `icon_length_rules` y clampeando `icon_scale_pct` a 10-100 en vez de un tamaño de fuente libre.

**3. `utils/PdfDesignHelper.ts`**

Junto a `resolveFontSizeRulesPayload` (línea ~823-830), agregar:

```ts
// Backend wire format for the icon-scale-by-grouped-text-length table (icon_size_rules, see
// PDF_ESCALA_ICONO_POR_TEXTO.md): same tiers as our own editable `icon_length_rules`, last tier's
// max_chars becomes `null`. Undefined (key omitted) when the toggle is off — the backend then just
// uses the fixed `icon_scale_pct`.
static resolveIconSizeRulesPayload(el: PdfDesignElement): PdfIconSizeRuleWire[] | undefined {
	if (!el.icon_length_rules_enabled || !el.icon_length_rules?.length) return undefined;
	const sorted = [...el.icon_length_rules].sort((a, b) => a.max_chars - b.max_chars);
	return sorted.map((rule, i) => ({
		max_chars: i === sorted.length - 1 ? null : rule.max_chars,
		icon_scale_pct: Math.min(100, Math.max(10, rule.icon_scale_pct))
	}));
}
```

**4. `pages/productPdfDesigns/edit/[id].vue`**

En el bloque `if (base.type === 'icon')` (línea ~632-652), junto a `editable_field: PdfDesignHelper.resolveEditableField(base)`, agregar:

```ts
icon_size_rules: PdfDesignHelper.resolveIconSizeRulesPayload(base),
```

## Backend — ya está listo

- El elemento `type: "icon"` ya acepta `icon_size_rules` (array de `{ max_chars, icon_scale_pct }`, `max_chars: null` = para el resto) y lo sanea/guarda.
- Ejemplo ya funcional para probar sin esperar la UI (Postman/curl), agregando esto al elemento ícono de un diseño existente que ya tenga `group_id` compartido con un texto:

```json
{
  "id": "icono1",
  "type": "icon",
  "group_id": "group-1794553276369-7-1610",
  "icon_scale_pct": 100,
  "icon_size_rules": [
    { "max_chars": 4, "icon_scale_pct": 100 },
    { "max_chars": 10, "icon_scale_pct": 60 },
    { "max_chars": null, "icon_scale_pct": 30 }
  ]
}
```
