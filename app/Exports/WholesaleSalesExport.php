<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class WholesaleSalesExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths
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
        return Sale::with([
            'client',
            'products.product',
            'products.variant',
            'status',
            'paymentMethod',
            'shippingMethod',
            'channel',
            'locality',
            'coupon',
        ])
            ->whereHas('client', function ($query) {
                $query->where('client_type_id', 2);
            })
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'N° Compra',
            'Fecha',
            'Estado',
            'Nombre Cliente',
            'Apellido Cliente',
            'Email',
            'Teléfono',
            'CUIT',
            'Razón Social',
            'Producto',
            'Variante',
            'Personalización',
            'Cantidad',
            'Precio Unitario',
            'Subtotal Línea',
            'Subtotal Compra',
            'Descuento',
            'Costo Envío',
            'Total',
            'Método de Pago',
            'Método de Envío',
            'Dirección',
            'Código Postal',
            'Localidad',
            'Cupón',
        ];
    }

    public function map($sale): array
    {
        $rows = [];

        foreach ($sale->products as $item) {
            $customization = '';
            if (!empty($item->customization_data)) {
                $parts = [];
                foreach ($item->customization_data as $key => $value) {
                    if (is_array($value)) {
                        $parts[] = $key . ': ' . implode(', ', $value);
                    } else {
                        $parts[] = $key . ': ' . $value;
                    }
                }
                $customization = implode(' | ', $parts);
            }

            $rows[] = [
                $sale->id,
                $sale->created_at->format('d/m/Y H:i'),
                $sale->status->name ?? '',
                $sale->client->name ?? '',
                $sale->client->lastName ?? '',
                $sale->client->email ?? '',
                $sale->client->phone ?? '',
                $sale->client->cuit ?? '',
                $sale->client->business_name ?? '',
                $item->product->name ?? '',
                $item->variant?->variant ?? '',
                $customization,
                $item->quantity,
                $item->unit_price,
                $item->quantity * $item->unit_price,
                $sale->subtotal,
                $sale->discount_amount,
                $sale->shipping_cost,
                $sale->total,
                $sale->paymentMethod->name ?? '',
                $sale->shippingMethod->name ?? '',
                $sale->address ?? '',
                $sale->postal_code ?? '',
                $sale->locality?->name ?? '',
                $sale->coupon->code ?? '',
            ];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'B' => 18, // Fecha
            'D' => 22, // Nombre
            'E' => 22, // Apellido
            'F' => 30, // Email
            'G' => 16, // Teléfono
            'H' => 16, // CUIT
            'I' => 30, // Razón Social
            'J' => 35, // Producto
            'K' => 20, // Variante
            'L' => 40, // Personalización
        ];
    }
}
