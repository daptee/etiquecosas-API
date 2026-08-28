<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <title>Tu carrito te espera</title>
  <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet" media="screen">
  <style>
    body { margin: 0; padding: 0; }
    table { border-collapse: collapse; width: 100%; }
    a { text-decoration: none; }

    @media (max-width: 600px) {
      .sm-w-full { width: 100% !important; }
      img { max-width: 100% !important; height: auto !important; }
    }
  </style>
</head>

<body style="margin: 0; width: 100%; padding: 0; word-break: break-word; -webkit-font-smoothing: antialiased; background-color: #ffffff;">
  <div style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly; display:none;">Tu carrito te espera</div>

  <table style="width:100%; font-family: Montserrat, -apple-system, 'Segoe UI', sans-serif;" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
      <td align="center">
        <table class="sm-w-full" style="width:600px; margin:0 auto;" cellpadding="0" cellspacing="0" role="presentation">

          <!-- LOGO -->
          <tr>
            <td align="center" style="padding:20px;">
              <a href="{{ config('services.front_url') }}">
                <img src="https://api.etiquecosaslab.com.ar/icons/mail/etiquecosas_logo-rosa.png" width="130" alt="Etiquecosas" style="max-width:100%; vertical-align:middle; border:0;">
              </a>
            </td>
          </tr>

          <!-- SALUDO -->
          <tr>
            <td align="center" style="background-color:#347AA7; padding:14px 20px;">
              <p style="margin:0; font-size:18px; line-height:140%; color:#ffffff; font-weight:700;">
                ¡{{ $sale->client->name }}!, no pierdas tu carrito!
              </p>
            </td>
          </tr>

          <!-- BANNERS -->
          <tr>
            <td align="center">
              <a href="{{ config('services.front_url') }}/categorias/combos" target="_blank">
                <img src="https://app2.dopplerfiles.com/Users/169304/Shared/cinta-info-cuotas-rosa.gif" alt="3 cuotas sin interés sin mínimo de compra · Envío gratis a partir de $95.000" width="600" style="width:100%; max-width:600px; display:block; border:0;">
              </a>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:10px;">
              <a href="{{ config('services.front_url') }}/categorias/combos" target="_blank">
                <img src="https://app2.dopplerfiles.com/Users/169304/Shared/Gemini_Generated_Image_diulzfdiulzfdiul.jpg" alt="Etiquecosas" width="580" style="width:100%; max-width:580px; display:block; border:0;">
              </a>
            </td>
          </tr>

          <!-- CTA -->
          <tr>
            <td align="center" style="padding:10px;">
              <a href="{{ config('services.front_url') }}" target="_blank"
                style="display:inline-block; padding:12px 28px; font-size:17px; font-weight:700; color:#ffffff; background-color:#347AA7; border-radius:30px; font-family:'Montserrat', sans-serif; text-decoration:none;">
                Terminar mi pedido
              </a>
            </td>
          </tr>

          <!-- URGENCIA -->
          <tr>
            <td align="center" style="padding:16px 24px; font-size:14px; line-height:140%; color:#444;">
              <p style="margin:0;">No queremos ser fatalistas pero realmente el stock VUELA</p>
              <p style="margin:0;">¡No te quedes sin tu pedido! 👁️👁️</p>
            </td>
          </tr>

          <!-- PRODUCTOS DEL CARRITO -->
          @foreach($sale->products as $item)
            @php
              $mainImage = optional($item->product)->images
                ? ($item->product->images->firstWhere('is_main', true) ?? $item->product->images->first())
                : null;
              $imageUrl = $mainImage->img ?? null;
              if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
                  $imageUrl = 'https://api.etiquecosas.com.ar/public/' . $imageUrl;
              }
              $productUrl = $item->product
                ? config('services.front_url') . '/productos/' . $item->product->slug
                : config('services.front_url');
            @endphp
            <tr>
              <td style="padding:10px 16px;">
                <table cellpadding="0" cellspacing="0" role="presentation" style="width:100%; border:1px solid #eee; border-radius:8px;">
                  <tr>
                    <td width="40%" style="padding:10px; vertical-align:top;">
                      @if($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $item->product->name ?? 'Producto' }}" style="width:100%; max-width:200px; display:block; border:0;">
                      @endif
                    </td>
                    <td width="60%" style="padding:10px; vertical-align:top; font-size:13px; color:#444;">
                      <span style="display:block; font-weight:700; font-size:13px;">{{ $item->product->name ?? ('ID producto: ' . ($item->product_id ?? '-')) }}</span>
                      <span style="display:block; margin-top:6px; color:#666;">Cantidad: {{ $item->quantity }}</span>
                      <span style="display:block; margin-top:6px; font-size:14px; color:#347AA7; font-weight:700;">${{ number_format((float)$item->unit_price * $item->quantity,0,',','.') }}</span>
                      <a href="{{ $productUrl }}" target="_blank"
                        style="display:inline-block; margin-top:12px; padding:6px 18px; font-size:13px; font-weight:700; color:#ffffff; background-color:#347AA7; border-radius:30px; text-decoration:none;">
                        Ver más
                      </a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          @endforeach

          <!-- BANNER CONTACTO -->
          <tr>
            <td align="center" style="padding:10px;">
              <a href="{{ config('services.front_url') }}" target="_blank">
                <img src="https://app2.dopplerfiles.com/Users/169304/Shared/WhatsApp_Image_2026-02-28_at_17.57.47.jpeg" alt="Etiquecosas" width="580" style="width:100%; max-width:580px; display:block; border:0;">
              </a>
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
                <a href="https://etiquecosas.com.ar/terminos" style="color:#347AA7; text-decoration:none;">Términos de uso</a> y
                <a href="https://etiquecosas.com.ar/privacidad" style="color:#347AA7; text-decoration:none;">Política de privacidad</a>.
              </p>

              <p style="margin:12px 0 4px;">
                <a href="https://www.etiquecosas.com.ar" style="color:#347AA7; font-weight:600; text-decoration:none;">www.etiquecosas.com.ar</a>
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
