<!DOCTYPE html>
<html lang="es">

<head>
 <meta charset="UTF-8">
 <title>Solicitud de pago sin factura</title>
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
  }

  .header {
   background: transparent;
   padding: 0;
   margin: 0;
   text-align: center;
  }

  .header h1 {
   margin: 0;
  }

  .logo {
   max-width: 80px;
   height: auto;
   margin-bottom: 15px;
   filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
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
   @include('emails.partials.app-header', ['title' => 'Solicitud de pago sin factura'])
  </div>

  <div class="content">
   <p>Hola <strong>{{ $notifiable->name }}</strong>,</p>

   <div class="alert-box">
    Tu solicitud de pago aún no cuenta con una factura asociada.
   </div>

   @include('emails.partials.spp-summary', [
    'sppFolio' => $solicitudPagoFolio,
    'sppEstado' => 'Sin factura',
    'sppFecha' => now(),
   ])

   <p>
    Para continuar con el proceso de pago, es necesario que subas la factura correspondiente
    en el sistema.
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
