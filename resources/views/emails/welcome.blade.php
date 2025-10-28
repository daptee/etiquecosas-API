<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <!--[if mso]>
    <xml><o:officedocumentsettings><o:pixelsperinch>96</o:pixelsperinch></o:officedocumentsettings></xml>
  <![endif]-->
  <title>Bienvenido a Etiquecosas</title>
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap" rel="stylesheet" media="screen">
  <style>
    .hover-underline:hover {
      text-decoration: underline !important;
    }

    @media (max-width: 600px) {
      .sm-w-full { width: 100% !important; }
      .sm-px-24 { padding-left: 24px !important; padding-right: 24px !important; }
      .sm-py-32 { padding-top: 32px !important; padding-bottom: 32px !important; }
    }
  </style>
</head>

<body style="margin: 0; width: 100%; padding: 0; word-break: break-word; -webkit-font-smoothing: antialiased; background-color: #f9f9f9;">
  <div style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly; display: none;">¡Bienvenido a Etiquecosas!</div>
  <div role="article" aria-roledescription="email" aria-label="Bienvenida Etiquecosas" lang="es" style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly;">
    <table style="width: 100%; font-family: Montserrat, -apple-system, 'Segoe UI', sans-serif;" cellpadding="0" cellspacing="0" role="presentation">
      <tr>
        <td align="center" style="background-color: #f9f9f9; font-family: Montserrat, -apple-system, 'Segoe UI', sans-serif;">
          <table class="sm-w-full" style="width: 600px;" cellpadding="0" cellspacing="0" role="presentation">
            
            <!-- LOGO -->
            <tr>
              <td class="sm-py-32 sm-px-24" style="padding: 48px; text-align: center;">
                <a href="{{ config('services.url_front') }}" style="font-family: 'Montserrat', sans-serif;">
                  <img src="https://etiquecosas.com.ar/logo.png" width="180" alt="Etiquecosas" style="max-width: 100%; vertical-align: middle; border: 0;">
                </a>
              </td>
            </tr>

            <!-- CONTENIDO -->
            <tr>
              <td align="center" class="sm-px-24">
                <table style="width: 100%;" cellpadding="0" cellspacing="0" role="presentation">
                  <tr>
                    <td class="sm-px-24" style="border-radius: 10px; background-color: #ffffff; padding: 48px; text-align: left; font-size: 16px; line-height: 26px; color: #444;">
                      
                      <p style="margin: 0; font-size: 18px; font-weight: 600;">¡Bienvenida/o a la web de <strong>Etiquecosas</strong>!</p>

                      <p style="margin-top: 16px;">Creamos automáticamente tu usuario para que puedas seguir de cerca tus pedidos y aprovechar todos los beneficios de tener tu cuenta activa.</p>

                      <p style="margin-top: 24px;">👉 Tu usuario es este mail registrado:<br>
                        <strong>{{ $user->email }}</strong>
                      </p>
                      <p>👉 Tu contraseña temporal es:<br>
                        <strong style="color: #EBA4AB;">{{ $temporary_password }}</strong>
                      </p>

                      <p style="margin-top: 24px;">Te recomendamos ingresar y cambiarla por una que te resulte fácil de recordar 💪</p>

                      <p style="margin-top: 24px;">Al iniciar sesión vas a poder:</p>
                      <ul style="padding-left: 20px; margin: 12px 0 24px;">
                        <li>✨ Ver el estado de tus pedidos</li>
                        <li>📦 Asociar nuevas compras a las existentes</li>
                        <li>📝 Modificar pedidos o direcciones de envío</li>
                        <li>💬 Gestionar reclamos fácilmente</li>
                      </ul>

                      <table cellpadding="0" cellspacing="0" role="presentation" style="margin-top: 24px;">
                        <tr>
                          <td style="border-radius: 4px; background-color: #EBA4AB;">
                            <a href="{{ config('services.url_front') }}/login" style="display: block; padding: 14px 28px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; font-family: 'Montserrat', sans-serif;">Acceder a mi cuenta</a>
                          </td>
                        </tr>
                      </table>

                      <p style="margin-top: 32px;">¡Gracias por elegirnos!<br>El equipo de <strong>Etiquecosas</strong> 💛</p>

                    </td>
                  </tr>

                  <!-- FOOTER -->
                  <tr>
                    <td style="height: 24px;"></td>
                  </tr>
                  <tr>
                    <td style="padding: 0 48px; font-size: 14px; color: #999; text-align: center;">
                      <p style="margin-bottom: 16px;">
                        <a href="https://www.instagram.com/etiquecosas" style="color: #EBA4AB; text-decoration: none; margin-right: 12px;">
                          <img width="20" height="20" src="https://smartagro.io/firmas/instagram.png" alt="Instagram" style="vertical-align: middle; border: 0;">
                        </a>
                        &bull;
                        <a href="mailto:hola@etiquecosas.com.ar" style="color: #EBA4AB; text-decoration: none; margin-left: 12px;">hola@etiquecosas.com.ar</a>
                      </p>
                      <p style="color: #bbb;">© {{ date('Y') }} Etiquecosas — Todos los derechos reservados.</p>
                    </td>
                  </tr>

                </table>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
