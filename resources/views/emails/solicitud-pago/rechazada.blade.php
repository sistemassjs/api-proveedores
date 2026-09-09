<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Solicitud de pago rechazada</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: #333333;
      background-color: #f8f9fa;
    }
    
    .email-container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #ffffff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .header {
      background: transparent;
      padding: 0;
      margin: 0;
      text-align: center;
    }
    
    .logo {
      max-width: 80px;
      height: auto;
      margin-bottom: 15px;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
    }
    
    .header h1 {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .header p {
      font-size: 14px;
      opacity: 0.9;
    }
    
    .content {
      padding: 30px 20px;
    }
    
    .greeting {
      font-size: 18px;
      font-weight: 500;
      margin-bottom: 20px;
      color: #2c3e50;
    }
    
    .intro-text {
      font-size: 16px;
      margin-bottom: 25px;
      color: #555;
    }
    
    .solicitud-card {
      background-color: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 8px;
      padding: 20px;
      margin: 25px 0;
    }
    
    .solicitud-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 2px solid #f44336;
    }
    
    .solicitud-folio {
      font-size: 20px;
      font-weight: 600;
      color: #f44336;
    }
    
    .solicitud-status {
      background-color: #f44336;
      color: #ffffff;
      padding: 5px 15px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .details-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-bottom: 15px;
    }
    
    .detail-item {
      display: flex;
      align-items: center;
    }
    
    .detail-label {
      font-weight: 600;
      color: #495057;
      margin-right: 8px;
    }
    
    .detail-value {
      color: #6c757d;
    }
    
    .warning-badge {
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      color: #721c24;
      padding: 15px;
      border-radius: 6px;
      margin: 20px 0;
      text-align: center;
      font-weight: 600;
    }
    
    .warning-badge .icon {
      font-size: 40px;
      margin-bottom: 10px;
    }
    
    .motivo-box {
      background-color: #fff3cd;
      border: 1px solid #ffeaa7;
      border-radius: 6px;
      padding: 15px;
      margin: 20px 0;
    }
    
    .motivo-label {
      font-weight: 600;
      color: #856404;
      margin-bottom: 8px;
      display: block;
    }
    
    .motivo-text {
      color: #533f03;
      font-style: italic;
      line-height: 1.6;
    }
    
    .action-button {
      display: inline-block;
      background: linear-gradient(135deg, #f44336 0%, #e57373 100%);
      color: #ffffff;
      padding: 15px 30px;
      text-decoration: none;
      border-radius: 6px;
      font-weight: 600;
      font-size: 16px;
      text-align: center;
      margin: 25px 0;
      transition: all 0.3s ease;
    }
    
    .action-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(244, 67, 54, 0.3);
    }
    
    .footer-text {
      font-size: 14px;
      color: #6c757d;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #e9ecef;
    }
    
    .footer {
      background-color: #f1f5f9;
      color: #475569;
      padding: 20px;
      text-align: center;
      font-size: 12px;
    }
    
    .info-box {
      background-color: #e3f2fd;
      border-left: 4px solid #2196f3;
      padding: 15px;
      margin: 20px 0;
      border-radius: 0 6px 6px 0;
    }
    
    .info-box-label {
      font-weight: 600;
      color: #1565c0;
      margin-bottom: 5px;
    }
    
    @media only screen and (max-width: 600px) {
      .email-container {
        margin: 0;
        border-radius: 0;
      }
      
      .details-grid {
        grid-template-columns: 1fr;
        gap: 10px;
      }
      
      .solicitud-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }
      
      .content {
        padding: 20px 15px;
      }
    }
  </style>
</head>
<body>
  <div class="email-container">
    <!-- Header -->
    <div class="header">
      @include('emails.partials.app-header', ['title' => 'Solicitud de pago rechazada'])
    </div>
    
    <!-- Content -->
    <div class="content">
      <div class="greeting">
                Hola {{ $notifiable->name }},
      </div>
      
      <div class="warning-badge">
        <div>Tu solicitud de pago fue rechazada</div>
      </div>
      
      <div class="intro-text">
        Tu solicitud de pago no fue aprobada. 
        Por favor revisa el motivo y las observaciones para realizar las correcciones necesarias.
      </div>
      
      @include('emails.partials.spp-summary', [
        'sppFolio' => $solicitudPagoFolio,
        'sppEstado' => 'Rechazada',
        'sppFecha' => now(),
      ])

      <div class="solicitud-card">
        @if($motivo)
        <div class="motivo-box">
          <span class="motivo-label">💬 Motivo del rechazo:</span>
          <div class="motivo-text">{{ $motivo }}</div>
        </div>
        @endif
      </div>
      
      <div class="info-box">
        <div class="info-box-label">💡 Próximos pasos:</div>
        <div>Revisa el motivo del rechazo, realiza las correcciones necesarias y vuelve a enviar tu solicitud de pago. Si tienes dudas, contacta al equipo de finanzas.</div>
      </div>
      
      <!-- Action Button -->
      <div style="text-align: center;">
        <a href="{{ $urlSolicitud }}" class="action-button">
          Ver Detalles de la Solicitud
        </a>
      </div>
      
      <div class="footer-text">
        <p><strong>¿Necesitas ayuda</strong> Si tienes dudas sobre el motivo del rechazo o necesitas orientación, no dudes en contactarnos.</p>
        
        <p style="margin-top: 15px;">
                    Gracias por ser parte de {{ config('app.name') }}.
          Tu colaboración es fundamental para el éxito de nuestros proyectos.
        </p>
      </div>
    </div>
    
    <div class="footer">@include('emails.partials.app-footer')</div>
  </div>
</body>
</html>
