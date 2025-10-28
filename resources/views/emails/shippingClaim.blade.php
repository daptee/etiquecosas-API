<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <title>Reclamo de envío</title>
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
                    <h2 style="color:#2c3e50; margin-bottom:20px;">Reclamo de envío</h2>
                    <p style="font-size:15px; color:#333;">El cliente <strong>{{ $clientName }}</strong> realizó un reclamo sobre el pedido <strong>#{{ $orderId }}</strong>.</p>

                    <div style="margin-top:20px; padding:15px; background:#f9f9f9; border-left:4px solid #e74c3c;">
                      <p style="margin:0; font-size:14px; color:#555;"><strong>Detalle del reclamo:</strong></p>
                      <p style="margin:0; font-size:14px; color:#000;">{{ $clientMessage }}</p>
                    </div>

                    <p style="margin-top:30px; font-size:13px; color:#777;">Este es un correo automático de notificación.</p>
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
</body>
</html>
