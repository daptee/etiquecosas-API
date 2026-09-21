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
     * Valores de atributo "reales" de esta línea de venta: los puntuales de la
     * variante tal cual, y para cada atributo comodín ("Todos") de la variante,
     * el valor que el cliente eligió (selected_attributes) — o "Todos" si no hay
     * selección registrada.
     */
    public function getResolvedAttributesValuesAttribute()
    {
        $variantModel = $this->variant;
        if (!$variantModel) {
            return collect();
        }

        $variantData = $variantModel->variant ?? [];

        $concreteEntries = collect($variantData['attributesvalues'] ?? []);
        $concreteIds = $concreteEntries->pluck('id')->filter();
        $concreteValues = AttributeValue::whereIn('id', $concreteIds)->with('attribute')->get()->keyBy('id');

        $resolvedConcrete = $concreteEntries->map(function ($entry) use ($concreteValues) {
            $av = $concreteValues->get($entry['id'] ?? null);
            if (!$av) {
                return null;
            }
            return [
                'id' => $av->id,
                'value' => $av->value,
                'attribute' => ['id' => $av->attribute->id ?? null, 'name' => $av->attribute->name ?? null],
            ];
        })->filter();

        $wildcardEntries = collect($variantData['available_attributes'] ?? []);
        $selections = collect($this->selected_attributes ?? []);
        $attributes = Attribute::whereIn('id', $wildcardEntries->pluck('attribute_id'))->get()->keyBy('id');
        $selectedValueIds = $selections->pluck('attribute_value_id')->filter();
        $selectedValues = AttributeValue::whereIn('id', $selectedValueIds)->get()->keyBy('id');

        $resolvedWildcard = $wildcardEntries->map(function ($entry) use ($attributes, $selections, $selectedValues) {
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
        });

        return $resolvedConcrete->concat($resolvedWildcard)->values();
    }
}
