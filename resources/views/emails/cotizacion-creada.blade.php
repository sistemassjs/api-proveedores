<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Cotización Solicitada</title>
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
        
        .cotizacion-card {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .cotizacion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #FFC107;
        }
        
        .cotizacion-id {
            font-size: 20px;
            font-weight: 600;
            color: #FFC107;
        }
        
        .cotizacion-total {
            font-size: 18px;
            font-weight: 600;
            color: #28a745;
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
        
        .observaciones {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .observaciones-label {
            font-weight: 600;
            color: #856404;
            margin-bottom: 5px;
        }
        
        .observaciones-text {
            color: #533f03;
            font-style: italic;
        }
        
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #FFC107 0%, #FFD54F 100%);
            color: #000000;
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
            box-shadow: 0 6px 12px rgba(255, 193, 7, 0.3);
        }
        
        .solicitante-info {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        
        .solicitante-label {
            font-weight: 600;
            color: #1565c0;
            margin-bottom: 5px;
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
        
        .icon {
            width: 16px;
            height: 16px;
            margin-right: 5px;
            vertical-align: middle;
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
            
            .cotizacion-header {
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
            @include('emails.partials.app-header', ['title' => 'Nueva cotización solicitada'])
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="greeting">
                ¡Hola {{ $notifiable->name }}!
            </div>
            
            <div class="intro-text">
                Se ha creado una nueva solicitud de cotización desde el módulo de 
                <strong>{{ ucfirst($moduloOrigen) }}</strong>. Te pedimos que revises 
                los detalles y respondas lo antes posible.
            </div>
            
            <!-- Cotización Card -->
            <div class="cotizacion-card">
                <div class="cotizacion-header">
                    <div class="cotizacion-id">
                        Cotización #{{ $cotizacion->id }}
                    </div>
                    <div class="cotizacion-total">
                        ${{ number_format($cotizacion->total, 2) }}
                    </div>
                </div>
                
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">📅 Fecha:</span>
                        <span class="detail-value">{{ $cotizacion->fecha_cotizacion->copy()->locale('es')->timezone('America/Mexico_City')->translatedFormat('j \\d\\e F \\d\\e Y') }} {{ $cotizacion->fecha_cotizacion->copy()->timezone('America/Mexico_City')->format('h:i') }} {{ $cotizacion->fecha_cotizacion->copy()->timezone('America/Mexico_City')->format('A') === 'AM' ? 'a.m.' : 'p.m.' }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">⏰ Vence:</span>
                        <span class="detail-value">{{ $cotizacion->fecha_vencimiento->copy()->locale('es')->timezone('America/Mexico_City')->translatedFormat('j \\d\\e F \\d\\e Y') }} {{ $cotizacion->fecha_vencimiento->copy()->timezone('America/Mexico_City')->format('h:i') }} {{ $cotizacion->fecha_vencimiento->copy()->timezone('America/Mexico_City')->format('A') === 'AM' ? 'a.m.' : 'p.m.' }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">📦 Productos:</span>
                        <span class="detail-value">{{ $cotizacion->detalles->count() }} artículos</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🏢 Módulo:</span>
                        <span class="detail-value">{{ ucfirst($moduloOrigen) }}</span>
                    </div>
                </div>
                
                @if($cotizacion->observaciones)
                <div class="observaciones">
                    <div class="observaciones-label">💬 Observaciones:</div>
                    <div class="observaciones-text">{{ $cotizacion->observaciones }}</div>
                </div>
                @endif
            </div>
            
            <!-- Solicitante Info -->
            <div class="solicitante-info">
                <div class="solicitante-label">👤 Solicitado por:</div>
                <div><strong>{{ $solicitante->name }}</strong></div>
                <div>{{ $solicitante->email }}</div>
            </div>
            
            <!-- Action Button -->
            <div style="text-align: center;">
                <a href="{{ $urlCotizacion }}" class="action-button">
                    Ver Detalles de la Cotización
                </a>
            </div>
            
            <div class="footer-text">
                <p><strong>¡Importante!</strong> Esta cotización tiene fecha de vencimiento 
                {{ $cotizacion->fecha_vencimiento->copy()->locale('es')->timezone('America/Mexico_City')->translatedFormat('j \\d\\e F \\d\\e Y') }} {{ $cotizacion->fecha_vencimiento->copy()->timezone('America/Mexico_City')->format('h:i') }} {{ $cotizacion->fecha_vencimiento->copy()->timezone('America/Mexico_City')->format('A') === 'AM' ? 'a.m.' : 'p.m.' }}. Te recomendamos responder 
                antes de esa fecha.</p>
                
                <p style="margin-top: 15px;">
                    ¡Gracias por ser parte de {{ config('app.name') }}! 
                    Tu participación es fundamental para el éxito de nuestros proyectos.
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">@include('emails.partials.app-footer')</div>
    </div>
</body>
</html>
