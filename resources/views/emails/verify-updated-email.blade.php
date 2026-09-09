<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validación de correo electrónico</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            max-width: 80px;
            height: auto;
            margin-bottom: 16px;
        }
        .header-title {
            color: #000000;
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }
        .content { padding: 36px 30px; }
        .welcome-text {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message {
            font-size: 16px;
            color: #555555;
            margin-bottom: 20px;
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
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.35);
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #FFC107;
            padding: 16px;
            border-radius: 4px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 24px 20px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        .footer-text {
            color: #999999;
            font-size: 13px;
            margin: 5px 0;
        }
        @media only screen and (max-width: 600px) {
            .content { padding: 28px 20px; }
            .header-title { font-size: 24px; }
            .cta-button { padding: 14px 30px; font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            @include('emails.partials.app-header', ['title' => 'Valida tu correo'])
        </div>

        <div class="content">
            @if($userName)
                <p class="welcome-text">Hola, {{ $userName }}.</p>
            @else
                <p class="welcome-text">Hola.</p>
            @endif

            <p class="message">
                Detectamos una actualización en tu correo electrónico. Para activar tu cuenta con este correo, confirma tu dirección con el siguiente botón.
            </p>

            <div class="cta-container">
                <a href="{{ $url }}" class="cta-button">Validar correo electrónico</a>
            </div>

            <div class="info-box">
                <p class="message" style="margin-bottom:0;font-size:14px;">
                    Este enlace estará disponible por <strong>24 horas</strong>. Si no realizaste este cambio, ignora este correo.
                </p>
            </div>
        </div>

        <div class="footer">
            @include('emails.partials.app-footer')
        </div>
    </div>
</body>
</html>
