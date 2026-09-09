<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Asociación con Empresa</title>
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
        
        .header-icon {
            font-size: 48px;
            margin-bottom: 15px;
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
        
        .empresa-card {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .empresa-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3880ff;
        }
        
        .empresa-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #3880ff 0%, #5a95ff 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 28px;
            color: white;
        }
        
        .empresa-info h2 {
            font-size: 20px;
            font-weight: 600;
            color: #3880ff;
            margin-bottom: 5px;
        }
        
        .empresa-info p {
            font-size: 14px;
            color: #6c757d;
        }
        
        .details-list {
            list-style: none;
            padding: 0;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-icon {
            margin-right: 12px;
            color: #3880ff;
            font-size: 18px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
            margin-right: 8px;
            min-width: 120px;
        }
        
        .detail-value {
            color: #6c757d;
            flex: 1;
        }
        
        .success-badge {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: center;
            font-weight: 600;
        }
        
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #3880ff 0%, #5a95ff 100%);
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
            box-shadow: 0 6px 12px rgba(56, 128, 255, 0.3);
        }
        
        .info-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        
        .info-box-label {
            font-weight: 600;
            color: #856404;
            margin-bottom: 5px;
        }
        
        .info-box-content {
            color: #856404;
            font-size: 14px;
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
        
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            
            .empresa-header {
                flex-direction: column;
                text-align: center;
            }
            
            .empresa-icon {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .detail-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .detail-label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            @include('emails.partials.app-header', ['title' => 'Nueva asociación con empresa', 'subtitle' => 'Tu red de proveedores está creciendo'])
        </div>
        
        <!-- Content -->
        <div class="content">
            <p class="greeting">Hola {{ $proveedorNombre }},</p>
            
            <p class="intro-text">
                Nos complace informarte que has sido vinculado con una nueva empresa constructora. 
                Ahora podrás gestionar solicitudes de pago y colaborar con ellos.
            </p>
            
            <!-- Empresa Card -->
            <div class="empresa-card">
                <div class="empresa-header">
                    <div class="empresa-icon">🏗️</div>
                    <div class="empresa-info">
                        <h2>{{ $empresaNombre }}</h2>
                        <p>Empresa Constructora</p>
                    </div>
                </div>
                
                <ul class="details-list">
                    <li class="detail-item">
                        <span class="detail-icon">📄</span>
                        <span class="detail-label">RFC:</span>
                        <span class="detail-value">{{ $empresaRfc }}</span>
                    </li>
                    <li class="detail-item">
                        <span class="detail-icon">👤</span>
                        <span class="detail-label">Vinculado por:</span>
                        <span class="detail-value">{{ $usuarioConstruccNombre }}</span>
                    </li>
                    <li class="detail-item">
                        <span class="detail-icon">📅</span>
                        <span class="detail-label">Fecha:</span>
                        <span class="detail-value">{{ now()->locale('es')->timezone('America/Mexico_City')->translatedFormat('j \\d\\e F \\d\\e Y') }} {{ now()->timezone('America/Mexico_City')->format('h:i') }} {{ now()->timezone('America/Mexico_City')->format('A') === 'AM' ? 'a.m.' : 'p.m.' }}</span>
                    </li>
                </ul>
            </div>
            
            <!-- Success Badge -->
            <div class="success-badge">
                ✅ Asociación exitosa con {{ $empresaNombre }}
            </div>
            
            <!-- Info Box -->
            <div class="info-box">
                <div class="info-box-label">¿Qué significa esto?</div>
                <div class="info-box-content">
                    A partir de ahora, podrás recibir y gestionar solicitudes de pago de esta empresa. 
                    También podrás visualizar el historial de transacciones con ellos.
                </div>
            </div>
            
            <!-- Action Button -->
            <center>
                <a href="{{ $urlEmpresa }}" class="action-button">
                    Ver Detalles de la Empresa
                </a>
            </center>
            
            <!-- Footer Text -->
            <p class="footer-text">
                Si tienes alguna pregunta o necesitas asistencia, no dudes en contactarnos. 
                Estamos aquí para ayudarte.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">@include('emails.partials.app-footer')</div>
    </div>
</body>
</html>
