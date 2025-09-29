<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Retirado</title>
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
        a {
            color: #ffb800;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>¡Tu pedido fue retirado! 🎉</h1>

        <p>Hola <span class="highlight">{{ $sale->client->name }} {{ $sale->client->lastname }}</span>,</p>

        <p>¡Tu pedido <span class="highlight">#{{ $sale->id }}</span> ya fue retirado en nuestro local! 🎉</p>

        <p>Esperamos que lo disfrutes mucho.</p>

        <p>Muchas gracias por elegir <strong>Etiquecosas</strong> 💛.</p>

        <p>Si tenés alguna consulta o necesitás algo más, podés escribirnos a través del <a href="https://etiquecosaslab.com.ar/">formulario de atención al cliente</a> en nuestra web.</p>

        <p>👉 ¡Nos encanta que formes parte de nuestra comunidad y esperamos verte pronto de nuevo!</p>
    </div>
</body>
</html>
