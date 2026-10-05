<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva solicitud de cotización</title>
</head>
<body style="font-family: Segoe UI, Tahoma, sans-serif; color: #222; background: #f6f8f7; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 24px;">
        <h2 style="margin: 0 0 12px; color: #00a878;">Nueva solicitud de cotización</h2>
        <p>Hola {{ $notifiable->name ?? 'equipo' }},</p>
        <p>
            Recibiste la solicitud <strong>{{ $solicitud->folio }}</strong> de
            <strong>{{ $solicitud->cliente_nombre }}</strong>
            ({{ $solicitud->cliente_email }}).
        </p>
        <p>Total de referencia: <strong>${{ number_format((float) $solicitud->total, 2) }}</strong></p>
        <p style="margin: 24px 0;">
            <a href="{{ $urlDetalle }}"
               style="background:#00a878;color:#fff;padding:12px 18px;border-radius:6px;text-decoration:none;">
                Ver en NexProv
            </a>
        </p>
        <p style="font-size: 12px; color: #777;">Notificación exclusiva de NexProv.</p>
    </div>
</body>
</html>
