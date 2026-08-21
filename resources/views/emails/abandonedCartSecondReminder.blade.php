<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <title>15% OFF para tu compra</title>
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap" rel="stylesheet" media="screen">
  <style>
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 10px; text-align: left; word-wrap: break-word; word-break: break-word; }
    th { background: #f3f3f3; white-space: nowrap; font-weight: 600; }
    .muted { color: #666; font-size: 0.9em; }

    @media (max-width: 600px) {
      .sm-w-full { width: 100% !important; }
      .sm-px-24 { padding-left: 24px !important; padding-right: 24px !important; }
      .sm-py-32 { padding-top: 32px !important; padding-bottom: 32px !important; }
    }
  </style>
</head>

<body style="margin: 0; width: 100%; padding: 0; word-break: break-word; -webkit-font-smoothing: antialiased; background-color: #ECEFF1;">
  <div style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly; display:none;">Último aviso: 15% OFF + envío gratis</div>

  <table style="width:100%; font-family: Montserrat, -apple-system, 'Segoe UI', sans-serif;" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
      <td align="center" style="background-color:#ECEFF1;">
         <table class="sm-w-full" style="width:600px; margin:0 auto;" cellpadding="0" cellspacing="0" role="presentation">
          <!-- LOGO -->
          <tr>
            <td class="sm-py-32 sm-px-24" style="padding:48px; text-align:center;">
              <a href="{{ config('services.front_url') }}">
                <img src="https://api.etiquecosaslab.com.ar/icons/mail/etiquecosas_logo-rosa.png" width="180" alt="Etiquecosas" style="max-width:100%; vertical-align:middle; border:0;">
              </a>
            </td>
          </tr>

          <!-- CONTENIDO -->
          <tr>
            <td align="center" class="sm-px-24" style="border-radius:10px; background-color:#ffffff;">
              <table style="width:100%;" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                  <td class="sm-px-24" style="padding:48px; text-align:left; font-size:16px; line-height:26px; color:#444;">

                    <p style="font-size:20px; font-weight:600; margin-bottom:0;">Hola</p>
                    <p style="font-size:20px; font-weight:700; color:#347AA7; margin-top:0;">
                      {{ $sale->client->name }} {{ $sale->client->lastname }}
                    </p>
                    <p style="font-size:18px; font-weight:600; margin-bottom:16px;">¡Todavía estás a tiempo! ⏳</p>
                    <p style="margin: 0 0 16px 0;">Tu carrito sigue esperándote y queremos ayudarte a terminarlo. Usá este cupón antes de que se acabe:</p>

                    <!-- Cupón -->
                    <div style="text-align:center; margin: 24px 0; padding: 20px; border: 2px dashed #EBA4AB; border-radius: 10px; background-color: #FFF8F9;">
                      <p style="margin:0; font-size:14px; color:#777;">Cupón de descuento</p>
                      <p style="margin:4px 0; font-size:28px; font-weight:700; color:#EBA4AB; letter-spacing:1px;">{{ $coupon->code }}</p>
                      <p style="margin:0; font-size:16px; font-weight:600;">15% OFF + envío gratis</p>
                    </div>

                    <p style="margin: 0 0 16px 0; font-size: 14px; color:#666;">Esta es tu última notificación para completar la compra con este beneficio.</p>

                    <!-- Productos -->
                    <h2 style="margin-top:24px; color:#347AA7; font-size: 16px;">Lo que quedó en tu carrito</h2>
                    <table>
                      <thead>
                        <tr>
                          <th style="font-size: 14px;">Producto</th>
                          <th style="font-size: 14px;">Cant.</th>
                          <th style="font-size: 14px;">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($sale->products as $item)
                          <tr>
                            <td style="font-size: 14px;">{{ $item->product->name ?? ('ID producto: ' . ($item->product_id ?? '-')) }}</td>
                            <td style="font-size: 14px; white-space: nowrap;">{{ $item->quantity }}</td>
                            <td style="font-size: 14px; white-space: nowrap;">${{ number_format((float)$item->unit_price * $item->quantity,0,',','.') }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>

                    <table style="width: 100%; font-size: 16px; border-collapse: collapse; border: 1px solid #e4e0e0; margin-top: 16px;">
                      <tr>
                        <td colspan="2" style="background-color: #F4F4F4; padding: 12px 10px; font-weight: bold;">
                          <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                              <td><strong>Total:</strong></td>
                              <td style="text-align: right;">${{ number_format($sale->total,0,',','.') }}</td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Botón -->
                    <div style="text-align:center; margin-top:32px;">
                      <table cellpadding="0" cellspacing="0" role="presentation" align="center">
                        <tr>
                          <td style="border-radius:6px; background-color:#EBA4AB; text-align:center; ">
                            <a href="{{ config('services.front_url') }}"
                              style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:600; color:#ffffff; text-decoration:none; font-family:'Montserrat', sans-serif;">
                              APROVECHAR MI DESCUENTO
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
            <td style="padding:40px 24px; text-align:center; color:#999; font-size:14px;">
              <p style="margin-bottom:16px;">
                <a href="https://www.instagram.com/etiquecosas" style="margin:0 6px;">
                  <img width="24" height="24" src="https://api.etiquecosaslab.com.ar/icons/mail/instagram_solid.png" alt="Instagram" style="vertical-align:middle; border:0;">
                </a>
                <a href="https://www.facebook.com/etiquecosas" style="margin:0 6px;">
                  <img width="24" height="24" src="https://api.etiquecosaslab.com.ar/icons/mail/facebook_solid.png" alt="Facebook" style="vertical-align:middle; border:0;">
                </a>
                <a href="https://www.youtube.com/@etiquecosas" style="margin:0 6px;">
                  <img width="24" height="24" src="https://api.etiquecosaslab.com.ar/icons/mail/youtube_solid.png" alt="YouTube" style="vertical-align:middle; border:0;">
                </a>
              </p>

              <p style="margin:8px 0; color:#777;">
                El uso de nuestro servicio y sitio web está sujeto a nuestros<br>
                <a href="https://etiquecosas.com.ar/terminos" style="color:#EBA4AB; text-decoration:none;">Términos de uso</a> y
                <a href="https://etiquecosas.com.ar/privacidad" style="color:#EBA4AB; text-decoration:none;">Política de privacidad</a>.
              </p>

              <p style="margin:12px 0 4px;">
                <a href="https://www.etiquecosas.com.ar" style="color:#EBA4AB; font-weight:600; text-decoration:none;">www.etiquecosas.com.ar</a>
              </p>
              <p style="font-size:12px; color:#aaa; margin:0;">Desarrollado por <strong>Daptee</strong></p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
