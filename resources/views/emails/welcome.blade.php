<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <title>Bienvenido a Etiquecosas</title>
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap" rel="stylesheet" media="screen">
  <style>
    .hover-underline:hover { text-decoration: underline !important; }
    @media (max-width: 600px) {
      .sm-w-full { width: 100% !important; }
      .sm-px-24 { padding-left: 24px !important; padding-right: 24px !important; }
      .sm-py-32 { padding-top: 32px !important; padding-bottom: 32px !important; }
    }
  </style>
</head>

<body style="margin: 0; width: 100%; padding: 0; word-break: break-word; -webkit-font-smoothing: antialiased; background-color: #ECEFF1;">
  <div style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly; display: none;">¡Bienvenido a Etiquecosas!</div>
  <div role="article" aria-roledescription="email" aria-label="Bienvenida Etiquecosas" lang="es" style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly;">
    <table style="width: 100%; font-family: Montserrat, -apple-system, 'Segoe UI', sans-serif;" cellpadding="0" cellspacing="0" role="presentation">
      <tr>
        <td align="center" style="background-color: #ECEFF1;">
          <table class="sm-w-full" style="width: 600px;" cellpadding="0" cellspacing="0" role="presentation">

            <!-- LOGO -->
            <tr>
              <td class="sm-py-32 sm-px-24" style="padding: 48px; text-align: center;">
                <a href="{{ config('services.front_url') }}">
                  <img src="https://api.etiquecosaslab.com.ar/icons/mail/etiquecosas_logo-rosa.png" width="180" alt="Etiquecosas" style="max-width: 100%; vertical-align: middle; border: 0;">
                </a>
              </td>
            </tr>

            <!-- CONTENIDO -->
            <tr>
              <td align="center" class="sm-px-24" style="border-radius: 10px; background-color: #ffffff;">
                <table style="width: 100%;" cellpadding="0" cellspacing="0" role="presentation">
                  <tr>
                    <td class="sm-px-24" style="padding: 48px; text-align: left; font-size: 16px; line-height: 26px; color: #444;">
                      <p style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly; margin-bottom: 0; font-size: 20px; font-weight: 600;">Hola</p>
                      <p style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly; margin-top: 0; font-size: 24px; font-weight: 700; color: #347AA7;">{{ $name }}</p>
                      <p style="margin: 0; font-size: 18px; font-weight: 600;">¡Bienvenida/o a la web de <strong>Etiquecosas</strong>!</p>

                      @if(!empty($temporary_password))
                        <p style="margin-top: 16px;">Creamos automáticamente tu usuario para que puedas seguir de cerca tus pedidos y aprovechar todos los beneficios de tener tu cuenta activa.</p>
                      @else
                        <p style="margin-top: 16px;">Ya creamos tu usuario para que puedas seguir de cerca tus pedidos y aprovechar todos los beneficios de tener tu cuenta activa.</p>
                      @endif

                      @if(!empty($temporary_password))
                        <p style="margin-top: 24px;">👉 Tu usuario es este mail registrado:<br>
                            <strong>{{ $email }}</strong>
                        </p>

                        <p>👉 Tu contraseña temporal es:<br>
                          <strong style="color: #347AA7;">{{ $temporary_password }}</strong>
                        </p>

                        <p style="margin-top: 24px;">Te recomendamos ingresar y cambiarla por una que te resulte fácil de recordar 💪</p>
                      @endif

                      <p style="margin-top: 24px;">Al iniciar sesión vas a poder:</p>
                      <ul style="padding-left: 20px; margin: 12px 0 24px;">
                        <li>✨ Ver el estado de tus pedidos</li>
                        <li>📦 Asociar nuevas compras a las existentes</li>
                        <li>📝 Modificar pedidos o direcciones de envío</li>
                        <li>💬 Gestionar reclamos fácilmente</li>
                      </ul>
                      
                      <div style="text-align: center; margin-top: 32px;">
                        <p style="margin: 0 0 24px 0; font-size: 16px;">
                          ¡Muchas gracias por elegir <strong>Etiquecosas</strong>! 💛
                        </p>

                        <table cellpadding="0" cellspacing="0" role="presentation" align="center">
                          <tr>
                            <td style="border-radius: 6px; background-color: #EBA4AB;">
                              <a href="{{ config('services.front_url') }}/iniciar-sesion"
                              style="display: inline-block; padding: 14px 28px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; font-family: 'Montserrat', sans-serif;">
                              IR A MI CUENTA
                              </a>
                            </td>
                          </tr>
                        </table>
                      </div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- FOOTER -->
            <tr>
              <td style="padding: 40px 24px; text-align: center; color: #999; font-size: 14px;">
                <p style="margin-bottom: 16px;">
                  <a href="https://www.instagram.com/etiquecosas" style="margin: 0 6px;">
                    <img width="24" height="24" src="https://api.etiquecosaslab.com.ar/icons/mail/instagram_solid.png" alt="Instagram" style="vertical-align: middle; border: 0;">
                  </a>
                  <a href="https://www.facebook.com/etiquecosas" style="margin: 0 6px;">
                    <img width="24" height="24" src="https://api.etiquecosaslab.com.ar/icons/mail/facebook_solid.png" alt="Facebook" style="vertical-align: middle; border: 0;">
                  </a>
                  <a href="https://www.youtube.com/@etiquecosas" style="margin: 0 6px;">
                    <img width="24" height="24" src="https://api.etiquecosaslab.com.ar/icons/mail/youtube_solid.png" alt="YouTube" style="vertical-align: middle; border: 0;">
                  </a>
                </p>

                <p style="margin: 8px 0; color: #777;">
                  El uso de nuestro servicio y sitio web está sujeto a nuestros<br>
                  <a href="https://etiquecosas.com.ar/terminos" style="color: #EBA4AB; text-decoration: none;">Términos de uso</a> y
                  <a href="https://etiquecosas.com.ar/privacidad" style="color: #EBA4AB; text-decoration: none;">Política de privacidad</a>.
                </p>

                <p style="margin: 12px 0 4px;">
                  <a href="https://www.etiquecosas.com.ar" style="color: #EBA4AB; font-weight: 600; text-decoration: none;">www.etiquecosas.com.ar</a>
                </p>
                <p style="font-size: 12px; color: #aaa; margin: 0;">Desarrollado por <strong>Daptee</strong></p>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
