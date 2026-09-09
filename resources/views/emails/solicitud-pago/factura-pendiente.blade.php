<!DOCTYPE html>
<html lang="es">

<head>
 <meta charset="UTF-8">
 <title>Factura pendiente de Solicitud de pago</title>
 <style>
  body {
   font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
   background-color: #f8f9fa;
   margin: 0;
   padding: 0;
  }

  .email-container {
   max-width: 600px;
   margin: auto;
   background: #ffffff;
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
   margin: 0;
  }

  .content {
   padding: 30px 20px;
  }

  .alert-box {
   background-color: #fff3cd;
   border-left: 4px solid #ff9800;
   padding: 15px;
   margin: 20px 0;
   border-radius: 4px;
   color: #6c4f00;
  }

  .details {
   background: #f8f9fa;
   border: 1px solid #e9ecef;
   border-radius: 8px;
   padding: 15px;
   margin: 20px 0;
  }

  .detail-item {
   margin: 6px 0;
  }

  .detail-label {
   font-weight: 600;
   color: #495057;
   margin-right: 6px;
  }

  .action-button {
   display: inline-block;
   background: #ff9800;
   color: #ffffff;
   padding: 15px 30px;
   text-decoration: none;
   border-radius: 6px;
   font-weight: 600;
  }

  .footer {
   background-color: #f1f5f9;
   color: #475569;
   text-align: center;
   padding: 15px;
   font-size: 12px;
  }
 </style>
</head>

<body>

 <div class="email-container">
  <div class="header">
   @include('emails.partials.app-header', ['title' => 'Factura pendiente'])
  </div>

  <div class="content">
   <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>

   <div class="alert-box">
    La solicitud de pago ya fue pagada, pero aún falta la factura correspondiente (CFDI).
   </div>

   @include('emails.partials.spp-summary', [
    'sppFolio' => $solicitudPagoFolio,
    'sppEstado' => 'Factura pendiente',
    'sppMonto' => $monto ?? null,
    'sppFecha' => now(),
   ])

   <p>
    Para completar el proceso, emite y sube el CFDI conforme a los datos fiscales indicados en el sistema.
   </p>

   <p style="text-align: center; margin: 30px 0;">
    <a href="{{ $urlSolicitud }}" class="action-button">
     Subir Factura
    </a>
   </p>

   <p>
    Si tienes dudas o consideras que esto es un error, por favor contáctanos.
   </p>
  </div>

  <div class="footer">@include('emails.partials.app-footer')</div>
 </div>

</body>

</html>
