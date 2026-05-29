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
        return [
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
            $sale->coupons->first()->code ?? '',
        ];
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
        ];
    }
}
