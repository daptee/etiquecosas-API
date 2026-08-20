<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Aviso al vendedor: pedidos en producción</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f5f5;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #ffffff;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .header {
      background-color: #fd7e14;
      color: white;
      padding: 20px;
      text-align: center;
    }
    .header h1 {
      margin: 0;
      font-size: 20px;
    }
    .content {
      padding: 24px;
    }
    .message {
      color: #333;
      font-size: 16px;
      line-height: 1.6;
      margin-bottom: 20px;
    }
    .sales-table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
    }
    .sales-table th {
      background-color: #f8f9fa;
      border: 1px solid #dee2e6;
      padding: 12px;
      text-align: left;
      font-weight: 600;
      color: #495057;
    }
    .sales-table td {
      border: 1px solid #dee2e6;
      padding: 12px;
      color: #333;
    }
    .sales-table tr:nth-child(even) {
      background-color: #f8f9fa;
    }
    .sale-id {
      font-weight: bold;
      color: #fd7e14;
    }
    .footer {
      background-color: #f8f9fa;
      padding: 16px;
      text-align: center;
      font-size: 12px;
      color: #6c757d;
      border-top: 1px solid #dee2e6;
    }
    .action-needed {
      background-color: #fff3cd;
      border: 1px solid #ffc107;
      border-radius: 4px;
      padding: 12px;
      margin-top: 20px;
      color: #856404;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>📦 Aviso al vendedor</h1>
    </div>

    <div class="content">
      <div class="message">
        @if($sales->count() === 1)
          <p>El siguiente pedido lleva <strong>{{ $days }} días</strong> en estado "En Producción". Te recomendamos contactar al cliente para informarle sobre el estado de su pedido:</p>
        @else
          <p>Los siguientes <strong>{{ $sales->count() }} pedidos</strong> llevan <strong>{{ $days }} días</strong> en estado "En Producción". Te recomendamos contactar a los clientes para informarles sobre el estado de sus pedidos:</p>
        @endif
      </div>

      <table class="sales-table">
        <thead>
          <tr>
            <th>Nº Pedido</th>
            <th>Cliente</th>
            <th>Email</th>
            <th>Fecha ingreso producción</th>
          </tr>
        </thead>
        <tbody>
          @foreach($sales as $sale)
          <tr>
            <td class="sale-id">#{{ $sale->id }}</td>
            <td>{{ $sale->client->name ?? 'Sin nombre' }} {{ $sale->client->lastname ?? '' }}</td>
            <td>{{ $sale->client->email ?? 'N/A' }}</td>
            <td>{{ $sale->production_entry_date ?? 'N/A' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>

      <div class="action-needed">
        <strong>Acción sugerida:</strong> Contactar al cliente para informarle sobre la demora y darle una estimación de entrega.
      </div>
    </div>

    <div class="footer">
      <p>Este es un mensaje automático del sistema de Etiquecosas.</p>
      <p>Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>
  </div>
</body>
</html>
