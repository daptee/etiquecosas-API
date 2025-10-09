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
            <p>Hola <strong>{{ $name }}</strong> 👋</p>

            <p>¡Bienvenido/a a <strong>Etiquecosas</strong>! 🎉</p>

            <div class="info">
                <p>Estamos muy felices de tenerte con nosotros. A partir de ahora vas a poder:</p>
                <p>✨ Crear y personalizar tus etiquetas fácilmente</p>
                <p>✨ Guardar tus pedidos y hacer seguimiento</p>
                <p>✨ Acceder a promociones y beneficios exclusivos</p>
            </div>

            <p>👉 Para comenzar, te recomendamos visitar tu perfil y completar tus datos para que la experiencia sea mucho más rápida y personalizada.</p>

            <p>Si tenés alguna duda, nuestro equipo está siempre listo para ayudarte 💬</p>
        </div>

        <div class="closing">
            <p>💛 <strong>Gracias por confiar en Etiquecosas</strong></p>
            <p>¡Estamos felices de que formes parte de nuestra comunidad!</p>
        </div>
    </div>
</body>
</html>
