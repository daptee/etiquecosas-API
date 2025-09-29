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
    </style>
</head>

<body>
    <div class="container">
        <div class="section">
            <p>Hola <strong>{{ $sale->client->name }} {{ $sale->client->lastname }}</strong>, 👋</p>

            <p>¡Buenas noticias! Tu pedido <strong>#{{ $sale->id }}</strong> ya entró en producción 🎉</p>

            <p>🔎 <strong>¿Qué significa?</strong><br>
               En esta etapa diseñamos y armamos tus etiquetas, las imprimimos y luego las revisamos para asegurarnos de que todo te llegue perfecto.</p>

            <p>⏳ <strong>Tiempo estimado:</strong> este proceso puede demorar hasta 10 días hábiles, pero si lo tenemos antes, te avisamos enseguida.</p>

            <p>Cuando tu pedido esté listo para enviar o retirar, te vamos a escribir nuevamente por este medio.</p>
        </div>

        <div class="closing">
            <p>💛 <strong>Muchas gracias por elegir Etiquecosas</strong></p>
        </div>
    </div>
</body>
</html>
