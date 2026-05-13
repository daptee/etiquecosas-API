<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <title>Pedido entregado por cadete</title>
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap" rel="stylesheet" media="screen">
  <style>
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 10px; text-align: left; word-wrap: break-word; word-break: break-word; }
    th { background: #f3f3f3; white-space: nowrap; font-weight: 600; }
    .muted { color: #666; font-size: 0.9em; }
  </style>
</head>

<body style="margin: 0; width: 100%; padding: 0; word-break: break-word; -webkit-font-smoothing: antialiased; background-color: #ECEFF1;">
  <div style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly; display:none;">Pedido entregado por cadete</div>

  <table style="width:100%; font-family: Montserrat, -apple-system, 'Segoe UI', sans-serif;" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
      <td align="center" style="background-color:#ECEFF1;">
        <table style="width:600px; margin:0 auto;" cellpadding="0" cellspacing="0" role="presentation">

          <!-- LOGO -->
          <tr>
            <td style="padding:48px; text-align:center;">
              <a href="{{ config('services.front_url') }}">
                <img src="https://api.etiquecosaslab.com.ar/icons/mail/etiquecosas_logo-rosa.png" width="180" alt="Etiquecosas" style="max-width:100%; vertical-align:middle; border:0;">
              </a>
            </td>
          </tr>

          <!-- CONTENIDO -->
          <tr>
            <td align="center" style="border-radius:10px; background-color:#ffffff;">
              <table style="width:100%;" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                  <td style="padding:48px; text-align:left; font-size:16px; line-height:26px; color:#444;">

                    <p style="font-size:18px; font-weight:600; margin-bottom:16px;">Pedido entregado por cadete</p>

                    <!-- Datos del cadete -->
                    <h2 style="margin-top:0; color:#347AA7; font-size:16px;">Cadete</h2>
                    <p style="margin:0; padding:0;"><strong>Nombre:</strong> {{ $cadete->name }} {{ $cadete->lastname ?? '' }}</p>
                    <p style="margin:0; padding:0;"><strong>Email:</strong> {{ $cadete->email }}</p>

                    <!-- Datos del pedido -->
                    <h2 style="margin-top:24px; color:#347AA7; font-size:16px;">Pedido #{{ $sale->id }}</h2>
                    <p style="margin:0; padding:0;"><strong>Cliente:</strong> {{ $sale->client->name }} {{ $sale->client->lastname }}</p>
                    <p style="margin:0; padding:0;"><strong>Email cliente:</strong> {{ $sale->client->email }}</p>
                    <p style="margin:0; padding:0;"><strong>Entregado el:</strong> {{ \Carbon\Carbon::parse($sale->delivered_at)->format('d/m/Y H:i') }}</p>

                    <!-- Datos del receptor -->
                    <h2 style="margin-top:24px; color:#347AA7; font-size:16px;">Receptor</h2>
                    <p style="margin:0; padding:0;"><strong>Nombre:</strong> {{ $sale->receiver_name }}</p>
                    <p style="margin:0; padding:0;"><strong>DNI:</strong> {{ $sale->receiver_dni }}</p>
                    @if($sale->receiver_observations)
                      <p style="margin:0; padding:0;"><strong>Observaciones:</strong> {{ $sale->receiver_observations }}</p>
                    @endif

                    <!-- Dirección de entrega -->
                    @if($sale->address)
                      <h2 style="margin-top:24px; color:#347AA7; font-size:16px;">Dirección de entrega</h2>
                      <p style="margin:0; padding:0;">{{ $sale->address }}@if($sale->locality), {{ $sale->locality->name }}@endif@if($sale->postal_code) (CP {{ $sale->postal_code }})@endif</p>
                    @endif

                    <!-- Botón -->
                    <div style="text-align:center; margin-top:32px;">
                      <table cellpadding="0" cellspacing="0" role="presentation" align="center">
                        <tr>
                          <td style="border-radius:6px; background-color:#EBA4AB; text-align:center;">
                            <a href="{{ config('services.front_url') }}/iniciar-sesion"
                              style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:600; color:#ffffff; text-decoration:none; font-family:'Montserrat', sans-serif;">
                              VER PEDIDO
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
