<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensaje de Contacto</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: none;
            padding: 0;
            margin: 0 0 24px 0;
            text-align: center;
        }
        .header .logo {
            max-width: 80px;
            height: auto;
            margin-bottom: 12px;
        }
        .header h1 {
            color: #0066cc;
            margin: 0;
            font-size: 24px;
        }
        .info-row {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 4px solid #0066cc;
        }
        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            min-width: 100px;
        }
        .info-value {
            color: #333;
        }
        .mensaje-box {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @include('emails.partials.app-header', ['title' => 'Nuevo mensaje de contacto'])
        </div>

        <div class="info-row">
            <span class="info-label">Nombre:</span>
            <span class="info-value">{{ $nombre }}</span>
        </div>

        <div class="info-row">
            <span class="info-label">Email:</span>
            <span class="info-value">
                <a href="mailto:{{ $email }}">{{ $email }}</a>
            </span>
        </div>

        @if($telefono)
        <div class="info-row">
            <span class="info-label">Teléfono:</span>
            <span class="info-value">{{ $telefono }}</span>
        </div>
        @endif

        @if($empresa)
        <div class="info-row">
            <span class="info-label">Empresa:</span>
            <span class="info-value">{{ $empresa }}</span>
        </div>
        @endif

        <h3 style="color: #0066cc; margin-top: 30px; margin-bottom: 10px;">Mensaje:</h3>
        <div class="mensaje-box">{{ $mensaje }}</div>

        <div class="footer">
            @include('emails.partials.app-footer')
        </div>
    </div>
</body>
</html>
