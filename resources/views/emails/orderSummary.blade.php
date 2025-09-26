<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            padding: 20px;
        }

        .container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            max-width: 700px;
            margin: auto;
        }

        h1 {
            color: #333;
        }

        h2 {
            margin-top: 30px;
            color: #444;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f3f3f3;
        }

        .total {
            font-weight: bold;
            font-size: 1.2em;
        }

        .highlight {
            color: #e63946;
            font-weight: bold;
        }

        .muted {
            color: #666;
            font-size: 0.9em;
        }

        .intro {
            margin-bottom: 20px;
            font-size: 1em;
        }

        .closing {
            margin-top: 30px;
            font-size: 1em;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Resumen de tu compra</h1>

        <!-- Mensaje inicial -->
        <div class="intro">
            <p>Hola <strong>{{ $sale->client->name }} {{ $sale->client->lastname }}</strong>, 👋</p>
            <p>¡Tu compra ingresó correctamente! 🎉<br>
               Ya aprobamos tu pedido <strong>#{{ $sale->id }}</strong> y en breve lo pondremos en marcha.</p>
            <p>Te vamos a estar avisando por este medio todas las novedades del proceso.</p>
        </div>

        <h2>Datos del cliente</h2>
        <p><strong>Nombre:</strong> {{ $sale->client->name }} {{ $sale->client->lastname }}</p>
        <p><strong>Email:</strong> {{ $sale->client->email }}</p>
        <p><strong>Teléfono:</strong> {{ $sale->client->phone }}</p>

        <h2>Productos</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:35%;">Producto</th>
                    <th style="width:25%;">Variante / Atributos</th>
                    <th style="width:20%;">Personalización</th>
                    <th style="width:5%;">Cant.</th>
                    <th style="width:7%;">Unit.</th>
                    <th style="width:8%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->products as $item)
                    <tr>
                        <!-- Producto -->
                        <td>
                            <strong>{{ $item->product->name ?? ('ID producto: ' . ($item->product_id ?? '-')) }}</strong><br>
                        </td>

                        <!-- Variante + atributos -->
                        <td>
                            @if(!empty($item['variant']))
                                <div><strong>SKU variante:</strong> {{ $item['variant']['variant']['sku'] ?? '-' }}</div>
                                @php
                                    $attrs = $item['variant']->attributes_values ?? collect();
                                @endphp
                                @if($attrs->count() > 0)
                                    @foreach($attrs as $attr)
                                        <div class="muted">
                                            {{ $attr->attribute->name ?? 'Atributo' }}: {{ $attr->value ?? '-' }}
                                        </div>
                                    @endforeach
                                @else
                                    <div class="muted">-</div>
                                @endif
                            @else
                                <div class="muted">Sin variante</div>
                            @endif
                        </td>

                        <!-- Personalización -->
                        <td>
                            @if($item->customization_data)
                                @php
                                    $custom = is_string($item->customization_data)
                                        ? json_decode($item->customization_data, true)
                                        : (array) $item->customization_data;
                                @endphp
                                @if(is_array($custom) && count($custom) > 0)
                                    @foreach($custom as $k => $v)
                                        <div>
                                            <strong>{{ ucfirst($k) }}:</strong>
                                            {{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v }}
                                        </div>
                                    @endforeach
                                @else
                                    <div class="muted">-</div>
                                @endif
                            @else
                                <div class="muted">-</div>
                            @endif
                        </td>

                        <!-- Cantidad -->
                        <td>{{ $item->quantity }}</td>

                        <!-- Precio unitario -->
                        <td>${{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>

                        <!-- Total línea -->
                        <td>${{ number_format((float) $item->unit_price * $item->quantity, 0, ',', '.') }}</td>
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

        <!-- Mensaje de cierre -->
        <div class="closing">
            <p>Muchas gracias por elegir <strong>Etiquecosas 💛</strong></p>
        </div>
    </div>
</body>
</html>
