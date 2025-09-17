<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambio de dirección</title>
</head>
<body style="background-color:#f4f4f4; font-family: Arial, sans-serif; margin:0; padding:20px;">
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="600" style="background:#ffffff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); padding:20px;">
                    <tr>
                        <td>
                            <h2 style="color:#2c3e50; margin-bottom:20px;">Solicitud de cambio de dirección</h2>
                            <p style="font-size:15px; color:#333;">El cliente <strong>{{ $clientName }}</strong> solicitó un cambio de dirección para el pedido <strong>#{{ $orderId }}</strong>.</p>

                            <div style="margin-top:20px; padding:15px; background:#f9f9f9; border-left:4px solid #27ae60;">
                                <p style="margin:0; font-size:14px; color:#555;"><strong>Nueva dirección:</strong></p>
                                <p style="margin:0; font-size:14px; color:#000;">{{ $newAddress }}</p>
                            </div>

                            @if($clientMessage)
                                <div style="margin-top:20px; padding:15px; background:#fefefe; border-left:4px solid #f39c12;">
                                    <p style="margin:0; font-size:14px; color:#555;"><strong>Comentario adicional:</strong></p>
                                    <p style="margin:0; font-size:14px; color:#000;">{{ $clientMessage }}</p>
                                </div>
                            @endif

                            <p style="margin-top:30px; font-size:13px; color:#777;">Este es un correo automático de notificación.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
