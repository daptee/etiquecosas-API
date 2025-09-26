<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Despachado</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #fdfdfd;
            color: #333333;
            line-height: 1.6;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 30px;
        }
        h1 {
            color: #ffb800;
        }
        p {
            margin-bottom: 15px;
        }
        .highlight {
            color: #ffb800;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>¡Tu pedido fue despachado! 🎉</h1>

        <p>Hola, <span class="highlight">{{ $sale->client->name }} {{ $sale->client->lastname }}</span> 👋</p>

        <p>¡Tu pedido <span class="highlight">#{{ $sale->id }}</span> ya fue despachado!</p>

        <p>📦 Si tu envío tiene número de seguimiento, te lo vamos a compartir para que puedas consultarlo.</p>

        <p>🚲 Si tu pedido va por cadetería (CABA), no podemos precisar el día y horario exacto porque depende del ruteo del cadete. Pero ¡tranqui! 👉 Si no estás en tu domicilio, el cadete te va a llamar al celular que dejaste al hacer la compra y coordinará sin costo un nuevo envío.</p>

        <p>Muy pronto vas a estar recibiendo tu pedido ✨</p>

        <p>💛 Muchas gracias por elegir <strong>Etiquecosas</strong></p>
    </div>
</body>
</html>
