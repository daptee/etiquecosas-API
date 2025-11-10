<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <title>Resumen de tu compra</title>
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap" rel="stylesheet" media="screen">
  <style>
    .hover-underline:hover { text-decoration: underline !important; }
    table { border-collapse: collapse; width: 100%; }
    th, td {
      padding: 10px;
      text-align: left;
      word-wrap: break-word;
      word-break: break-word;
    }
    th {
      background: #f3f3f3;
      white-space: nowrap;
      font-weight: 600;
    }
    .total { font-weight: bold; font-size: 1.2em; }
    .highlight { color: #EBA4AB; font-weight: bold; }
    .muted { color: #666; font-size: 0.9em; }

    .products-table th,
    .products-table td {
      vertical-align: top;
    }

    .products-table th:nth-child(1),
    .products-table td:nth-child(1) {
      width: 18%;
    }

    .products-table th:nth-child(2),
    .products-table td:nth-child(2) {
      width: 22%;
    }

    .products-table th:nth-child(3),
    .products-table td:nth-child(3) {
      width: 10%;
      white-space: nowrap !important;
    }

    .products-table th:nth-child(4),
    .products-table td:nth-child(4) {
      width: 22%;
      white-space: nowrap !important;
      min-width: 100px;
    }

    .products-table th:nth-child(5),
    .products-table td:nth-child(5) {
      width: 28%;
      white-space: nowrap !important;
      min-width: 120px;
    }

    .td-label {
      display: none;
    }

    @media (max-width: 600px) {
      .sm-w-full { width: 100% !important; }
      .sm-px-24 { padding-left: 24px !important; padding-right: 24px !important; }
      .sm-py-32 { padding-top: 32px !important; padding-bottom: 32px !important; }

      .products-table thead { display: none; }
      .products-table tbody { display: block; }
      .products-table tr {
        display: block;
        margin-bottom: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        background: #fafafa;
      }
      .products-table td {
        display: block;
        text-align: left !important;
        padding: 10px 0 !important;
        border: none !important;
        border-bottom: 1px solid #eee !important;
        width: 100% !important;
        white-space: normal !important;
      }
      .products-table td:last-child {
        border-bottom: none !important;
      }
      .td-label {
        display: block !important;
        font-weight: 700;
        margin-bottom: 5px;
        color: #347AA7;
        font-size: 13px;
        text-transform: uppercase;
      }
    }
  </style>
</head>

<body style="margin: 0; width: 100%; padding: 0; word-break: break-word; -webkit-font-smoothing: antialiased; background-color: #ECEFF1;">
  <div style="font-family: 'Montserrat', sans-serif; mso-line-height-rule: exactly; display:none;">Resumen de venta</div>

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

                    <p style="font-size:18px; font-weight:600; margin-bottom:16px;">¡Nueva venta aprobada! 🎉</p>

                    <!-- Datos del cliente -->
                    <h2 style="margin-top:24px; color:#347AA7; font-size: 16px;">Datos del cliente</h2>
                    <p style="margin: 0; padding: 0;"><strong>Nombre:</strong> {{ $sale->client->name }} {{ $sale->client->lastname }}</p>
                    <p style="margin: 0; padding: 0;"><strong>Email:</strong> {{ $sale->client->email }}</p>
                    <p style="margin: 0; padding: 0;"><strong>Teléfono:</strong> {{ $sale->client->phone }}</p>

                    <!-- Productos -->
                    <h2 style="margin-top:24px; color:#347AA7; font-size: 16px;">Productos</h2>
                    <table class="products-table">
                      <thead>
                        <tr>
                          <th style="font-size: 14px;">Detalle</th>
                          <th style="font-size: 14px;">Personalización</th>
                          <th style="font-size: 14px;">Cant.</th>
                          <th style="font-size: 14px;">Unit.</th>
                          <th style="font-size: 14px;">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($sale->products as $item)
                          <tr>
                            <td style="font-size: 14px;">
                              <span class="td-label">Detalle:</span>
                              <strong>{{ $item->product->name ?? ('ID producto: ' . ($item->product_id ?? '-')) }}</strong>
                            </td>
                            <td style="font-size: 14px;">
                              <span class="td-label">Personalización:</span>
                              @if($item->customization_data)
                                @php
                                  $custom = is_string($item->customization_data)
                                    ? json_decode($item->customization_data, true)
                                    : (array) $item->customization_data;
                                  $translations = ['color'=>'Color','icon'=>'Ícono','form'=>'Formulario'];
                                  $formTranslations = ['name'=>'Nombre','lastName'=>'Apellido'];
                                @endphp
                                @if(is_array($custom) && count($custom) > 0)
                                  @foreach($custom as $k=>$v)
                                    @php $label = $translations[$k] ?? ucfirst($k); @endphp
                                    @if($k==='color' && !empty($v['name']))
                                      <div><strong>{{ $label }}:</strong> {{ $v['name'] }}</div>
                                    @elseif($k==='icon' && !empty($v['name']))
                                      <div><strong>{{ $label }}:</strong> {{ $v['name'] }}</div>
                                    @elseif($k==='form' && is_array($v))
                                      @foreach($formTranslations as $fk=>$ft)
                                        @if(!empty($v[$fk]))
                                          <div><strong>{{ $ft }}:</strong> {{ $v[$fk] }}</div>
                                        @endif
                                      @endforeach
                                    @endif
                                  @endforeach
                                @else
                                  <div class="muted">-</div>
                                @endif
                              @else
                                <div class="muted">-</div>
                              @endif
                            </td>
                            <td style="font-size: 14px; white-space: nowrap;">
                              <span class="td-label">Cant.:</span>
                              <span style="white-space: nowrap;">{{ $item->quantity }}</span>
                            </td>
                            <td style="font-size: 14px; white-space: nowrap;">
                              <span class="td-label">Unit.:</span>
                              <span style="white-space: nowrap;">${{ number_format((float)$item->unit_price,0,',','.') }}</span>
                            </td>
                            <td style="font-size: 14px; white-space: nowrap;">
                              <span class="td-label">Total:</span>
                              <span style="white-space: nowrap;">${{ number_format((float)$item->unit_price * $item->quantity,0,',','.') }}</span>
                            </td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>

                    <table style="width: 100%; font-size: 16px; border-collapse: collapse; border: 1px solid #e4e0e0;">
                    <tr>
                        <td style="padding: 15px 10px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <!-- Subtotal -->
                            <tr>
                            <td style="padding: 8px 0;"><strong>Subtotal:</strong></td>
                            <td style="text-align: right; padding: 8px 0;">${{ number_format($sale->subtotal,0,',','.') }}</td>
                            </tr>

                            <!-- Descuento -->
                            @if(isset($sale->discount_percent) && $sale->discount_percent>0)
                            <tr>
                            <td style="padding: 8px 0;"><strong>Descuento ({{ $sale->discount_percent }}%):</strong></td>
                            <td style="text-align: right; padding: 8px 0;">-${{ number_format($sale->discount_amount,0,',','.') }}</td>
                            </tr>
                            @endif

                            <!-- Bloque de envío / método / dirección -->
                            <tr>
                            <td colspan="2" style="padding: 4px 0;">
                                <table style="width: 100%; border-collapse: collapse;">
                                <!-- Costo de envío -->
                                <tr>
                                    <td style="padding: 0;"><strong>Costo de envío:</strong></td>
                                    <td style="text-align: right; padding: 0;">
                                    @if($sale->shippingMethod->id !== 1)
                                        ${{ number_format($sale->shipping_cost,0,',','.') }}
                                    @else
                                        -
                                    @endif
                                    </td>
                                </tr>

                                <!-- Método -->
                                <tr>
                                    <td colspan="2" style="padding: 0;"><strong>Método:</strong> {{ $sale->shippingMethod->name }}</td>
                                </tr>

                                <!-- Dirección -->
                                @if($sale->shippingMethod->id !== 1)
                                <tr>
                                    <td colspan="2" style="padding: 0; font-size: 14px;">
                                    <strong>Dirección:</strong> {{ $sale->address }}, {{ $sale->locality->name }} (CP {{ $sale->postal_code }})
                                    </td>
                                </tr>
                                @endif
                                </table>
                            </td>
                            </tr>
                        </table>
                        </td>
                    </tr>

                    <!-- TOTAL -->
                    <tr>
                        <td colspan="2" style="background-color: #F4F4F4; padding: 12px 0px; font-weight: bold;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                            <td><strong>Total:</strong></td>
                            <td style="text-align: right;">${{ number_format($sale->total,0,',','.') }}</td>
                            </tr>
                        </table>
                        </td>
                    </tr>

                    <!-- Notas -->
                    <tr>
                        <td colspan="2" style="padding: 10px;">
                        <strong>Notas:</strong> {{ $sale->internal_comments ?? '-' }}
                        </td>
                    </tr>
                    </table>

                    <!-- Botón IR A MI CUENTA -->
                    <div style="text-align:center; margin-top:32px;">
                      <table cellpadding="0" cellspacing="0" role="presentation" align="center">
                        <tr>
                          <td style="border-radius:6px; background-color:#EBA4AB; text-align:center; ">
                            <a href="{{ config('services.front_url') }}/iniciar-sesion"
                              style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:600; color:#ffffff; text-decoration:none; font-family:'Montserrat', sans-serif;">
                              IR A LA COMPRA
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
