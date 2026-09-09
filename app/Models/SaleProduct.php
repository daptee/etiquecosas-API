<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleProduct extends Model
{
    protected $table = 'sales_products';

    protected $fillable = [
        'sale_id',
        'product_id',
        'variant_id',
        'customization_data',
        'selected_attributes',
        'quantity',
        'unit_price',
        'comment',
    ];

    protected $casts = [
        'customization_data' => 'array',
        'selected_attributes' => 'array',
        'variants' => 'array',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Valores de atributo "reales" de esta línea de venta: para cada entrada de la
     * variante, si es un valor puntual se devuelve tal cual; si es comodín ("Todos"
     * los valores de un atributo) se resuelve contra lo que el cliente eligió en
     * `selected_attributes`, y si no hay selección registrada se degrada a "Todos".
     */
    public function getResolvedAttributesValuesAttribute()
    {
        $rawEntries = collect($this->variant?->variant['attributesvalues'] ?? []);
        if ($rawEntries->isEmpty()) {
            return collect();
        }

        $concreteIds = $rawEntries->filter(fn($e) => !empty($e['id']))->pluck('id');
        $concreteValues = AttributeValue::whereIn('id', $concreteIds)->with('attribute')->get()->keyBy('id');

        $selections = collect($this->selected_attributes ?? []);
        $wildcardAttributeIds = $rawEntries->filter(fn($e) => empty($e['id']) && !empty($e['attribute_id']))->pluck('attribute_id');
        $attributes = Attribute::whereIn('id', $wildcardAttributeIds)->get()->keyBy('id');
        $selectedValueIds = $selections->pluck('attribute_value_id')->filter();
        $selectedValues = AttributeValue::whereIn('id', $selectedValueIds)->get()->keyBy('id');

        return $rawEntries->map(function ($entry) use ($concreteValues, $attributes, $selections, $selectedValues) {
            if (!empty($entry['id'])) {
                $av = $concreteValues->get($entry['id']);
                if (!$av) {
                    return null;
                }
                return [
                    'id' => $av->id,
                    'value' => $av->value,
                    'attribute' => ['id' => $av->attribute->id ?? null, 'name' => $av->attribute->name ?? null],
                ];
            }

            if (empty($entry['attribute_id'])) {
                return null;
            }

            $attribute = $attributes->get($entry['attribute_id']);
            $selection = $selections->firstWhere('attribute_id', $entry['attribute_id']);
            $selectedValue = $selection ? $selectedValues->get($selection['attribute_value_id'] ?? null) : null;

            return [
                'id' => $selectedValue->id ?? null,
                'value' => $selectedValue->value ?? 'Todos',
                'attribute' => [
                    'id' => $attribute->id ?? $entry['attribute_id'],
                    'name' => $attribute->name ?? null,
                ],
            ];
        })->filter()->values();
    }
}
