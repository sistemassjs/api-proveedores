<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Presupuesto rechazado</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; }
    .email-container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .header { background: transparent; padding: 0; margin: 0; text-align: center; }
    .logo { max-width: 80px; height: auto; margin-bottom: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); }
    .content { padding: 30px 20px; }
    .greeting { font-size: 18px; font-weight: 500; margin-bottom: 20px; color: #2c3e50; }
    .info-box { background: #f8f9fa; border-left: 4px solid #6c757d; padding: 15px; margin: 20px 0; border-radius: 4px; color: #495057; }
    .motivo-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px; color: #856404; }
    .action-button { display: inline-block; background: #6c757d; color: #fff; padding: 15px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
    .footer { background: #f1f5f9; color: #475569; padding: 15px; text-align: center; font-size: 12px; }
    .card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 16px; margin: 16px 0; }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="header">
      @include('emails.partials.app-header', ['title' => 'Presupuesto rechazado'])
    </div>
    <div class="content">
      <div class="greeting">Hola {{ $notifiable->name }},</div>
      <div class="info-box">
        {{ $presupuesto->empresa_receptora_empresa ?? $presupuesto->empresa_receptora_nombre ?? 'El cliente' }} rechazó el presupuesto #{{ $presupuesto->numero_presupuesto }}.
      </div>
      @if(!empty($motivoRechazo))
      <div class="motivo-box">
        <strong>Motivo indicado:</strong><br>
        {{ $motivoRechazo }}
      </div>
      @endif
      @include('emails.partials.provider-card', ['proveedor' => $presupuesto->proveedor, 'proveedorLogo' => $proveedorLogo ?? null])
      @include('emails.partials.presupuesto-summary', ['presupuesto' => $presupuesto])
      <p style="font-size:14px;color:#6c757d;">Adjuntamos el PDF del presupuesto rechazado.</p>
      <p><a href="{{ $urlDetalle }}" class="action-button">Ver detalle del presupuesto</a></p>
    </div>
    <div class="footer">@include('emails.partials.app-footer')</div>
  </div>
</body>
</html>
