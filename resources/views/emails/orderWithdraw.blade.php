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

        p {
            font-size: 1em;
            line-height: 1.5em;
        }

        .highlight {
            font-weight: bold;
        }

        .closing {
            margin-top: 30px;
            font-size: 1em;
        }

        .section {
            margin-bottom: 20px;
        }

        .info {
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="section">
            <p>Hola <strong>{{ $sale->client->name }} {{ $sale->client->lastname }}</strong> 👋</p>

            <p>¡Tu pedido ya está listo para retirar! 🎉</p>

            <div class="info">
                <p>📍 <strong>¿Dónde?</strong> Serrano 394, Villa Crespo – CABA</p>
                <p>🕐 <strong>¿Cuándo?</strong> De lunes a viernes, de 12 a 18 hs</p>
                <p>🔑 <strong>¿Qué necesito?</strong> Tu número de pedido: <strong>#{{ $sale->id }}</strong></p>
            </div>

            <div class="info">
                <p>➡️ Podés retirarlo vos u otra persona mayor de 18 años con el número de pedido.</p>
                <p>➡️ Si preferís, también podés coordinar una moto de Rappi o PedidosYa (llegan a nuestra zona sin problema).</p>
            </div>

            <p>👉 <strong>Recordá:</strong> el número de pedido es imprescindible para retirar.</p>

            <p>¡Te esperamos!</p>
        </div>

        <div class="closing">
            <p>💛 <strong>Muchas gracias por elegir Etiquecosas</strong></p>
        </div>
    </div>
</body>
</html>
