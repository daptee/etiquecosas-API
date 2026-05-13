<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Alerta de stock bajo</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f5f5;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 620px;
      margin: 0 auto;
      background-color: #ffffff;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .header {
      background-color: #f59e0b;
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
    .product-name {
      font-size: 18px;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 8px;
    }
    .message {
      color: #374151;
      font-size: 15px;
      line-height: 1.6;
      margin-bottom: 20px;
    }
    .alerts-table {
      width: 100%;
      border-collapse: collapse;
      margin: 16px 0;
    }
    .alerts-table th {
      background-color: #fef3c7;
      border: 1px solid #fcd34d;
      padding: 10px 12px;
      text-align: left;
      font-weight: 600;
      color: #92400e;
      font-size: 13px;
    }
    .alerts-table td {
      border: 1px solid #e5e7eb;
      padding: 10px 12px;
      color: #374151;
      font-size: 14px;
    }
    .alerts-table tr:nth-child(even) td {
      background-color: #fffbeb;
    }
    .stock-under {
      color: #dc2626;
      font-weight: 700;
    }
    .action-needed {
      background-color: #fef3c7;
      border: 1px solid #fcd34d;
      border-radius: 4px;
      padding: 12px 16px;
      margin-top: 20px;
      color: #92400e;
      font-size: 14px;
    }
    .footer {
      background-color: #f9fafb;
      padding: 14px;
      text-align: center;
      font-size: 12px;
      color: #6b7280;
      border-top: 1px solid #e5e7eb;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>⚠️ Alerta de Stock Bajo</h1>
    </div>

    <div class="content">
      <div class="product-name">{{ $product->name }}</div>

      <div class="message">
        @if(count($alerts) === 1)
          <p>El siguiente caso de stock se encuentra <strong>en o por debajo del umbral de alerta</strong>:</p>
        @else
          <p>Los siguientes <strong>{{ count($alerts) }} casos</strong> de stock se encuentran <strong>en o por debajo del umbral de alerta</strong>:</p>
        @endif
      </div>

      <table class="alerts-table">
        <thead>
          <tr>
            <th>Variante</th>
            <th>Canal</th>
            <th>Stock actual</th>
            <th>Umbral de alerta</th>
          </tr>
        </thead>
        <tbody>
          @foreach($alerts as $alert)
          <tr>
            <td>{{ $alert['variante'] }}</td>
            <td>{{ $alert['canal'] }}</td>
            <td class="stock-under">{{ $alert['stock_actual'] }} unidades</td>
            <td>{{ $alert['stock_alerta'] }} unidades</td>
          </tr>
          @endforeach
        </tbody>
      </table>

      <div class="action-needed">
        <strong>Acción requerida:</strong> Revisar el stock de este producto y reponer si es necesario para evitar quiebres de inventario.
      </div>
    </div>

    <div class="footer">
      <p>Este es un mensaje automático del sistema de Etiquecosas.</p>
      <p>Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>
  </div>
</body>
</html>
