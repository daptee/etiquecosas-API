<?php

namespace App\Exports;

use App\Models\Sale;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $sale = Sale::with(['client', 'products.product', 'products.variant', 'status', 'coupon', 'user'])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->get();

        Log::info($sale);

        return $sale;
    }

    public function headings(): array
    {
        return [
            'Sale ID',
            'Sale Date',
            'Client Name',
            'Client Email',
            'Client Phone',
            'Product Name',
            'Variant',
            'Quantity',
            'Unit Price',
            'Line Total',
            'Subtotal',
            'Shipping Cost',
            'Total',
            'Discount Amount',
            'Sale Status',
            'Coupon',
            'Channel',
            'User',
            'Payment Method',
            'Shipping Address',
            'Postal Code',
            'Locality'
        ];
    }

    public function map($sale): array
    {
        $rows = [];

        foreach ($sale->products as $productOrder) {
            $rows[] = [
                $sale->id,
                $sale->created_at->format('Y-m-d H:i:s'),
                $sale->client->name ?? '',
                $sale->client->email ?? '',
                $sale->client->phone ?? '',
                $productOrder->product->name ?? '',
                $productOrder->variant?->variant ?? '',
                $productOrder->quantity,
                $productOrder->unit_price,
                $productOrder->quantity * $productOrder->unit_price,
                $sale->subtotal,
                $sale->shipping_cost,
                $sale->total,
                $sale->discount_amount,
                $sale->status->name ?? '',
                $sale->coupon->code ?? '',
                $sale->channel->name ?? '',
                $sale->user->name ?? '',
                $sale->payment_method_id, // si quieres el nombre, ajusta
                $sale->address,
                $sale->postal_code,
                $sale->locality?->name ?? ''
            ];
        }

        return $rows;
    }
}
