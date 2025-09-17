<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; }
        .container { background: #fff; padding: 20px; border-radius: 10px; max-width: 700px; margin: auto; }
        h1 { color: #333; }
        h2 { margin-top: 30px; color: #444; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f3f3f3; }
        .total { font-weight: bold; font-size: 1.2em; }
        .highlight { color: #e63946; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Resumen de tu compra</h1>

        <h2>Datos del cliente</h2>
        <p><strong>Nombre:</strong> {{ $sale->client->name }} {{ $sale->client->lastname }}</p>
        <p><strong>Email:</strong> {{ $sale->client->email }}</p>
        <p><strong>Teléfono:</strong> {{ $sale->client->phone }}</p>

        <h2>Productos</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Variante</th>
                    <th>Personalización</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->products as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td>
                            @if($item->variant && isset($item->variant->variant->attributesvalues))
                                @foreach($item->variant->variant->attributesvalues as $attr)
                                    {{ $attr->attribute->name }}: {{ $attr->value }}<br>
                                @endforeach
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($item->customization_data)
                                @php $custom = json_decode($item->customization_data, true); @endphp
                                @foreach($custom as $key => $value)
                                    <strong>{{ ucfirst($key) }}:</strong> {{ $value }}<br>
                                @endforeach
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $item->quantity }}</td>
                        <td>${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                        <td>${{ number_format($item->unit_price * $item->quantity, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Envío</h2>
        <p><strong>Método:</strong> {{ $sale->shippingMethod->name }}</p>
        @if($sale->shippingMethod->id !== 1)
            <p><strong>Dirección:</strong> {{ $sale->address }}, {{ $sale->locality->name }} (CP {{ $sale->postal_code }})</p>
        @else
            <p><strong>Retiro en local</strong></p>
        @endif

        <h2>Resumen</h2>
        <p><strong>Subtotal:</strong> ${{ number_format($sale->subtotal, 0, ',', '.') }}</p>
        @if(isset($sale->discount_percent) && $sale->discount_percent > 0)
            <p><strong>Descuento ({{ $sale->discount_percent }}%):</strong> -${{ number_format($sale->discount_amount, 0, ',', '.') }}</p>
        @endif
        <p><strong>Costo de envío:</strong> ${{ number_format($sale->shipping_cost, 0, ',', '.') }}</p>
        <p class="total">Total: <span class="highlight">${{ number_format($sale->total, 0, ',', '.') }}</span></p>

        <h2>Notas</h2>
        <p><strong>Cliente:</strong> {{ $sale->customer_notes ?? '-' }}</p>
        <p><strong>Interno:</strong> {{ $sale->internal_comments ?? '-' }}</p>
    </div>
</body>
</html>
