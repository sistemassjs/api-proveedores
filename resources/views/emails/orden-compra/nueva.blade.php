<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Orden de Compra</title>
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
        
        .orden-card {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .orden-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4CAF50;
        }
        
        .orden-id {
            font-size: 20px;
            font-weight: 600;
            color: #4CAF50;
        }
        
        .orden-estatus {
            background-color: #FFC107;
            color: #000000;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .orden-estatus.pendiente {
            background-color: #FFC107;
        }
        
        .orden-estatus.aprobada {
            background-color: #4CAF50;
            color: #ffffff;
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
        
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 100%);
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
            box-shadow: 0 6px 12px rgba(76, 175, 80, 0.3);
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
            
            .orden-header {
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
            @include('emails.partials.app-header', ['title' => 'Nueva orden de compra'])
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="greeting">
                ¡Hola {{ $notifiable->name }}!
            </div>
            
            <div class="intro-text">
                Se ha generado una nueva orden de compra para tu empresa. 
                Por favor revisa los detalles y procede según corresponda.
            </div>
            
            <!-- Orden Card -->
            <div class="orden-card">
                <div class="orden-header">
                    <div class="orden-id">
                        Orden #{{ $ordenCompraId }}
                    </div>
                    <div class="orden-estatus {{ strtolower($estatus) }}">
                        {{ ucfirst($estatus) }}
                    </div>
                </div>
                
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">📅 Fecha:</span>
                        <span class="detail-value">{{ now()->locale('es')->timezone('America/Mexico_City')->translatedFormat('j \\d\\e F \\d\\e Y') }} {{ now()->timezone('America/Mexico_City')->format('h:i') }} {{ now()->timezone('America/Mexico_City')->format('A') === 'AM' ? 'a.m.' : 'p.m.' }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🏢 Proveedor ID:</span>
                        <span class="detail-value">{{ $proveedorId }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🏭 Empresa ID:</span>
                        <span class="detail-value">{{ $empresaId }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">📊 Estado:</span>
                        <span class="detail-value">{{ ucfirst($estatus) }}</span>
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <div class="info-box-label">💡 Acción requerida:</div>
                <div>Por favor accede al sistema para revisar el detalle completo de la orden de compra y tomar las acciones necesarias.</div>
            </div>
            
            <!-- Action Button -->
            <div style="text-align: center;">
                <a href="{{ $urlOrden }}" class="action-button">
                    Ver Detalles de la Orden
                </a>
            </div>
            
            <div class="footer-text">
                <p><strong>¡Importante!</strong> Mantén la comunicación activa con el equipo de compras para cualquier consulta o aclaración sobre esta orden.</p>
                
                <p style="margin-top: 15px;">
                    ¡Gracias por ser parte de {{ config('app.name') }}! 
                    Tu colaboración es fundamental para el éxito de nuestros proyectos.
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">@include('emails.partials.app-footer')</div>
    </div>
</body>
</html>
