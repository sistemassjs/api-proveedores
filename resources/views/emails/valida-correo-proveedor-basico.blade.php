<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirma tu registro</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background: transparent;
            padding: 0;
            margin: 0;
            text-align: center;
        }
        .logo {
            max-width: 100px;
            height: auto;
            margin-bottom: 15px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        .header-title {
            color: #000000;
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }
        .content {
            padding: 40px 30px;
        }
        .welcome-text {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .company-name {
            color: #FFC107;
            font-weight: 600;
        }
        .message {
            font-size: 16px;
            color: #555555;
            margin-bottom: 30px;
            line-height: 1.8;
        }
        .cta-container {
            text-align: center;
            margin: 35px 0;
        }
        .cta-button {
            display: inline-block;
            padding: 16px 40px;
            background: linear-gradient(135deg, #FFC107 0%, #FFD54F 100%);
            color: #000000 !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4);
            transition: transform 0.2s;
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.5);
        }
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e0e0e0, transparent);
            margin: 30px 0;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #FFC107;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 0;
            color: #666666;
            font-size: 14px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px 20px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        .footer-text {
            color: #999999;
            font-size: 13px;
            margin: 5px 0;
        }
        .footer-link {
            color: #FFC107;
            text-decoration: none;
        }
        .security-note {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 6px;
            margin-top: 25px;
        }
        .security-note p {
            color: #856404;
            font-size: 13px;
            margin: 0;
        }
        @media only screen and (max-width: 600px) {
            .content {
                padding: 30px 20px;
            }
            .header-title {
                font-size: 24px;
            }
            .cta-button {
                padding: 14px 30px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            @include('emails.partials.app-header', ['title' => '¡Bienvenido!'])
        </div>

        <!-- Contenido principal -->
        <div class="content">
            @if($nombreEmpresa)
            <p class="welcome-text">¡Hola, <span class="company-name">{{ $nombreEmpresa }}</span>!</p>
            @else
            <p class="welcome-text">¡Hola!</p>
            @endif
            
            <p class="message">
                @if($nombreEmpresa)
                Gracias por registrar <strong>{{ $nombreEmpresa }}</strong> en {{ config('app.name') }}.
                @else
                Gracias por registrarte en {{ config('app.name') }}.
                @endif
                Estamos encantados de tenerte como parte de nuestra red de colaboradores.
            </p>

            <p class="message">
                Para completar tu registro y acceder a todas las funcionalidades de la plataforma, 
                necesitas crear tu contraseña de acceso.
            </p>

            <!-- Botón de acción -->
            <div class="cta-container">
                <a href="{{ $url }}" class="cta-button">
                    Crear mi contraseña
                </a>
            </div>

            <div class="divider"></div>

            <!-- Información adicional -->
            <div class="info-box">
                <p>
                    <strong>¿Qué sigue después?</strong><br>
                    Una vez que establezcas tu contraseña, podrás acceder a tu panel de proveedor y gestionar:
                </p>
                <ul style="margin: 10px 0 0 20px; color: #666666; font-size: 14px;">
                    <li>Solicitudes de pago</li>
                    <li>Catálogo de productos</li>
                    <li>Información de tu empresa</li>
                    <li>Documentación fiscal</li>
                </ul>
            </div>

        </div>

        <div class="footer">
            @include('emails.partials.app-footer')
        </div>
    </div>
</body>
</html>
