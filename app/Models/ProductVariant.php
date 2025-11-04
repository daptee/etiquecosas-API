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

    public function toArray()
    {
        $array = parent::toArray();

        $array['variant'] = [
            'sku' => $this->variant['sku'] ?? null,
            'name' => $this->variant['name'] ?? null,
            'price' => $this->variant['price'] ?? null,
            'discounted_price' => $this->variant['discounted_price'] ?? null,
            'discounted_start' => $this->variant['discounted_start'] ?? null,
            'discounted_end' => $this->variant['discounted_end'] ?? null,
            'stock_status' => $this->variant['stock_status'] ?? null,
            'stock_quantity' => $this->variant['stock_quantity'] ?? null,
            'wholesale_price' => $this->variant['wholesale_price'] ?? null,
            'wholesale_min_amount' => $this->variant['wholesale_min_amount'] ?? null,
            'order' => $this->variant['order'] ?? null,
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
        ];

        return $array;
    }
}
