<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'variant',
        'stock_channels',
        'img',
    ];

    protected $casts = [
        'variant' => 'array',
        'stock_channels' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getAttributesValuesAttribute()
    {
        $ids = collect($this->variant['attributesvalues'] ?? [])
            ->pluck('id')
            ->filter()
            ->toArray();

        return AttributeValue::whereIn('id', $ids)->with('attribute')->get();
    }

    /**
     * Atributos "Todos" (comodín) de esta variante: por cada uno, el atributo y la
     * lista completa de valores disponibles que el producto tiene asociados.
     */
    public function getAvailableAttributesResolvedAttribute()
    {
        $entries = collect($this->variant['available_attributes'] ?? []);
        if ($entries->isEmpty()) {
            return collect();
        }

        $attributeIds = $entries->pluck('attribute_id');
        $attributes = Attribute::whereIn('id', $attributeIds)->get()->keyBy('id');

        return $entries->map(function ($entry) use ($attributes) {
            $attribute = $attributes->get($entry['attribute_id']);
            $values = AttributeValue::whereIn('id', $entry['available_value_ids'] ?? [])->get();

            return [
                'attribute' => [
                    'id' => $entry['attribute_id'],
                    'name' => $attribute->name ?? null,
                ],
                'values' => $values->map(fn($v) => ['id' => $v->id, 'value' => $v->value])->values()->toArray(),
            ];
        })->values();
    }

    private static function normalizeStockChannels(?array $channels): ?array
    {
        if (!$channels) return $channels;
        return array_map(function ($ch) {
            if (isset($ch['is_heritable'])) {
                $ch['is_heritable'] = (int) $ch['is_heritable'];
            }
            return $ch;
        }, $channels);
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['stock_channels'] = self::normalizeStockChannels($this->stock_channels);

        $array['variant'] = [
            'sku' => $this->variant['sku'] ?? null,
            'name' => $this->variant['name'] ?? null,
            'price' => $this->variant['price'] ?? null,
            'profit_percentage' => $this->variant['profit_percentage'] ?? null,
            'discounted_price' => $this->variant['discounted_price'] ?? null,
            'discount_percentage' => $this->variant['discount_percentage'] ?? null,
            'discounted_start' => $this->variant['discounted_start'] ?? null,
            'discounted_end' => $this->variant['discounted_end'] ?? null,
            'stock_status' => $this->variant['stock_status'] ?? null,
            'stock_quantity' => $this->variant['stock_quantity'] ?? null,
            'stock_alert' => $this->variant['stock_alert'] ?? null,
            'wholesale_price' => $this->variant['wholesale_price'] ?? null,
            'wholesale_min_amount' => $this->variant['wholesale_min_amount'] ?? null,
            'order' => $this->variant['order'] ?? null,
            'is_heritable' => isset($this->variant['is_heritable']) ? (int) $this->variant['is_heritable'] : null,
            'attributesvalues' => $this->attributes_values->map(function ($attr) {
                return [
                    'id' => $attr->id,
                    'value' => $attr->value,
                    'attribute' => [
                        'id' => $attr->attribute->id ?? null,
                        'name' => $attr->attribute->name ?? null,
                    ],
                ];
            })->toArray(),
            'available_attributes' => $this->available_attributes_resolved->toArray(),
        ];

        return $array;
    }
}
