<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Abono a Solicitud de pago</title>
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
      border-bottom: 2px solid #ff9800;
    }
    
    .solicitud-folio {
      font-size: 20px;
      font-weight: 600;
      color: #ff9800;
    }
    
    .solicitud-monto {
      font-size: 18px;
      font-weight: 600;
      color: #f57c00;
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
    
    .info-badge {
      background-color: #fff3cd;
      border: 1px solid #ffeeba;
      color: #856404;
      padding: 15px;
      border-radius: 6px;
      margin: 20px 0;
      text-align: center;
      font-weight: 600;
    }
    
    .info-badge .icon {
      font-size: 40px;
      margin-bottom: 10px;
    }
    
    .action-button {
      display: inline-block;
      background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
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
      box-shadow: 0 6px 12px rgba(255, 152, 0, 0.3);
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
      
      .solicitud-header-table td {
        display: block;
        width: 100%;
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
      @include('emails.partials.app-header', ['title' => 'Abono en solicitud de pago'])
    </div>
    
    <!-- Content -->
    <div class="content">
      <div class="greeting">
                Hola {{ $notifiable->name }},
      </div>
      
      <div class="info-badge">
        <div>Se registro un abono en tu solicitud de pago.</div>
      </div>
      
      <div class="intro-text">
        Se registro un abono en tu solicitud de pago. Puedes revisar el detalle y el saldo restante en el sistema.
        Puedes revisar el detalle y el saldo restante en el sistema.
      </div>
      
      @include('emails.partials.spp-summary', [
        'sppFolio' => $solicitudPagoFolio,
        'sppEstado' => 'Abonada',
        'sppMonto' => $montoAbonado ?? null,
        'sppFecha' => now(),
      ])
      
      <div class="info-box">
        <div class="info-box-label">💡 Información:</div>
        <div>Si necesitas el comprobante o el detalle completo, puedes consultarlo en el sistema.</div>
      </div>
      
      <!-- Action Button -->
      <div style="text-align: center;">
        <a href="{{ $urlSolicitud }}" class="action-button">
          Ver Detalles de la Solicitud
        </a>
      </div>
      
      <div class="footer-text">
                <p><strong>Gracias por tu paciencia.</strong> Si tienes alguna duda sobre este pago, no dudes en contactarnos.</p>
        
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
