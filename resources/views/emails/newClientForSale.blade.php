<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bienvenido</title>
    </head>
    <body>
        <p>Hola {{ $name }},</p>
        <p>Hemos recibido la solicitud para crear tu cuenta en Etiquecosas.</p>
        <p>Te dejamos a continuación tu nueva contraseña para que puedas acceder a la plataforma:</p>
        <p><strong>{{ $password }}</strong></p>
        <p>No olvides cambiarla cuando ingreses.</p>
        <p>Muchas gracias,</p>
        <p>El equipo IT de Etiquecosas.</p>
    </body>
</html>