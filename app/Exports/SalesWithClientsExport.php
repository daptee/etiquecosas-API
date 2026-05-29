<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class SalesWithClientsExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths
{
    protected $startDate;
    protected $endDate;
    protected $clientTypeId; // null = todos, 1 = minorista, 2 = mayorista

    public function __construct($startDate, $endDate, $clientTypeId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->clientTypeId = $clientTypeId;
    }

    public function collection()
    {
        $query = Sale::with([
            'client.clientType',
            'products.product',
            'products.variant',
            'status',
            'paymentMethod',
            'shippingMethod',
            'channel',
            'locality',
            'coupons',
        ])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->orderBy('created_at', 'asc');

        if ($this->clientTypeId !== null) {
            $query->whereHas('client', function ($q) {
                $q->where('client_type_id', $this->clientTypeId);
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'N° Venta',
            'Fecha',
            'Estado',
            'Nombre',
            'Apellido',
            'Email',
            'Teléfono',
            'Tipo de cliente',
            'CUIT',
            'Razón Social',
            'Producto',
            'Variante',
            'Personalización',
            'Cantidad',
            'Precio Unitario',
            'Subtotal Línea',
            'Subtotal',
            'Descuento',
            'Costo Envío',
            'Total',
            'Método de Pago',
            'Método de Envío',
            'Dirección',
            'Código Postal',
            'Localidad',
            'Canal',
            'Cupón',
        ];
    }

    public function map($sale): array
    {
        $rows = [];

        $couponCode = $sale->coupons->first()->code ?? '';

        foreach ($sale->products as $item) {
            $customization = $this->parseCustomization($item->customization_data);

            $rows[] = [
                $sale->id,
                $sale->created_at->format('d/m/Y H:i'),
                $sale->status->name ?? '',
                $sale->client->name ?? '',
                $sale->client->lastName ?? '',
                $sale->client->email ?? '',
                $sale->client->phone ?? '',
                $sale->client->clientType->name ?? '',
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
                $sale->channel->name ?? '',
                $couponCode,
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
            'H' => 18, // Tipo de cliente
            'I' => 16, // CUIT
            'J' => 30, // Razón Social
            'K' => 35, // Producto
            'L' => 20, // Variante
            'M' => 40, // Personalización
        ];
    }

    private function parseCustomization($customizationData): string
    {
        if (is_string($customizationData)) {
            $customizationData = json_decode($customizationData, true);
        }

        if (!is_array($customizationData) || empty($customizationData)) {
            return '';
        }

        $parts = [];
        foreach ($customizationData as $key => $value) {
            if (is_array($value)) {
                $flat = array_map(fn($v) => is_array($v) ? json_encode($v) : $v, $value);
                $parts[] = $key . ': ' . implode(', ', $flat);
            } else {
                $parts[] = $key . ': ' . $value;
            }
        }

        return implode(' | ', $parts);
    }
}
