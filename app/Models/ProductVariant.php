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

    private function getWildcardAttributesValues()
    {
        $wildcardAttributeIds = collect($this->variant['attributesvalues'] ?? [])
            ->filter(fn($v) => empty($v['id']) && !empty($v['attribute_id']))
            ->pluck('attribute_id')
            ->unique()
            ->values();

        if ($wildcardAttributeIds->isEmpty()) {
            return collect();
        }

        return Attribute::whereIn('id', $wildcardAttributeIds)->get()->map(fn($attribute) => [
            'id' => null,
            'value' => 'Todos',
            'attribute' => [
                'id' => $attribute->id,
                'name' => $attribute->name,
            ],
        ]);
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
            })->concat($this->getWildcardAttributesValues())->values()->toArray(),
        ];

        return $array;
    }
}
