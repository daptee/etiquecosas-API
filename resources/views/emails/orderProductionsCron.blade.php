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
        <h1>Tu pedido sigue en producción</h1>

        <div class="section">
            <p>Hola <strong>{{ $sale->client->name }}</strong> 👋</p>

            <p>Tu pedido <strong>#{{ $sale->id }}</strong> sigue en producción, pero ¡cada vez falta menos para que esté listo y llegue a tus manos! ✨</p>

            <p>Cuando esté terminado y preparado para enviar o retirar, te vamos a avisar por este medio.</p>
        </div>

        <div class="closing">
            <p>💛 <strong>Gracias por tu paciencia y por confiar en Etiquecosas</strong></p>
        </div>
    </div>
</body>
</html>
