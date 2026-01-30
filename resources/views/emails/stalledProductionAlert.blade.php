<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Alerta: Ventas estancadas en producción</title>
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
      background-color: #dc3545;
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
    .alert-icon {
      font-size: 48px;
      text-align: center;
      margin-bottom: 16px;
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
      color: #dc3545;
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
      <h1>⚠️ Alerta de Ventas Estancadas</h1>
    </div>

    <div class="content">
      <div class="message">
        @if($sales->count() === 1)
          <p>La siguiente venta lleva <strong>{{ $businessDays }} días hábiles</strong> en estado "En Producción":</p>
        @else
          <p>Las siguientes <strong>{{ $sales->count() }} ventas</strong> llevan <strong>{{ $businessDays }} días hábiles</strong> en estado "En Producción":</p>
        @endif
      </div>

      <table class="sales-table">
        <thead>
          <tr>
            <th>Nº Venta</th>
            <th>Cliente</th>
            <th>Fecha ingreso producción</th>
          </tr>
        </thead>
        <tbody>
          @foreach($sales as $sale)
          <tr>
            <td class="sale-id">#{{ $sale->id }}</td>
            <td>{{ $sale->client->name ?? 'Sin nombre' }} {{ $sale->client->lastname ?? '' }}</td>
            <td>{{ $sale->production_entry_date ?? 'N/A' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>

      <div class="action-needed">
        <strong>Acción requerida:</strong> Por favor, revisar estas ventas y retomar su producción para evitar demoras con los clientes.
      </div>
    </div>

    <div class="footer">
      <p>Este es un mensaje automático del sistema de Etiquecosas.</p>
      <p>Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>
  </div>
</body>
</html>
