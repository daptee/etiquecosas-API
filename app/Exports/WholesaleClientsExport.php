<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class WholesaleClientsExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths
{
    public function collection()
    {
        return Client::with(['wholesales.locality'])
            ->where('client_type_id', 2)
            ->orderBy('name')
            ->get();
    }

    public function columnWidths(): array
    {
        return [
            'B' => 25, // Nombre
            'C' => 25, // Apellido
            'D' => 35, // Email
            'E' => 18, // Teléfono
            'F' => 18, // CUIT
            'G' => 35, // Razón Social
        ];
    }

    public function headings(): array
    {
        return [
            'ID Cliente',
            'Nombre',
            'Apellido',
            'Email',
            'Teléfono',
            'CUIT',
            'Razón Social',
            'Nombre Local',
            'Dirección',
            'Código Postal',
            'Localidad',
        ];
    }

    public function map($client): array
    {
        $rows = [];

        if ($client->wholesales->isEmpty()) {
            $rows[] = [
                $client->id,
                $client->name,
                $client->lastName,
                $client->email,
                $client->phone,
                $client->cuit,
                $client->business_name,
                '',
                '',
                '',
                '',
            ];
        } else {
            foreach ($client->wholesales as $wholesale) {
                $rows[] = [
                    $client->id,
                    $client->name,
                    $client->lastName,
                    $client->email,
                    $client->phone,
                    $client->cuit,
                    $client->business_name,
                    $wholesale->name,
                    $wholesale->address,
                    $wholesale->postal_code,
                    $wholesale->locality?->name ?? '',
                ];
            }
        }

        return $rows;
    }
}
