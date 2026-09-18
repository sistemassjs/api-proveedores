<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completa tu registro</title>
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

        .content {
            padding: 40px 30px;
        }

        .welcome-text {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message {
            font-size: 16px;
            color: #555555;
            margin-bottom: 25px;
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

        @media only screen and (max-width: 600px) {
            .content {
                padding: 30px 20px;
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
            @include('emails.partials.app-header', ['title' => '¡Completa tu registro!'])
        </div>

        <div class="content">
            <p class="welcome-text">¡Hola!</p>

            <p class="message">
                Estás a un paso de completar tu registro en {{ config('app.name') }}.
            </p>

            {{-- Bloque de datos del proveedor --}}
            <div style="margin: 25px 0;">
                <div style="
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 8px;
                    padding: 20px;
                ">
                    <p style="
                        font-size: 13px;
                        font-weight: 600;
                        color: #888;
                        margin-bottom: 15px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    ">
                        Datos de registro de la empresa
                    </p>

                    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 15px;">
                        <tr>
                            <td style="color: #999; padding: 8px 0; width: 40%;">
                                Nombre comercial
                            </td>
                            <td style="color: #2c3e50; font-weight: 600; padding: 8px 0;">
                                {{ $proveedor->nombre_comercial }}
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2" style="border-bottom: 1px solid #eee;"></td>
                        </tr>

                        <tr>
                            <td style="color: #999; padding: 8px 0;">
                                Razón social
                            </td>
                            <td style="color: #2c3e50; font-weight: 600; padding: 8px 0;">
                                {{ $proveedor->razon_social }}
                            </td>
                        </tr>

                        <tr>
                            <td style="color: #999; padding: 8px 0;">
                                Correo electrónico
                            </td>
                            <td style="color: #2c3e50; font-weight: 600; padding: 8px 0;">
                                {{ $proveedor->email }}
                            </td>
                        </tr>
                        <tr>
                            <td style="color: #999; padding: 8px 0;">
                                Teléfono
                            </td>
                            <td style="color: #2c3e50; font-weight: 600; padding: 8px 0;">
                                {{ $proveedor->telefono_codigo_pais . ' ' . $proveedor->telefono }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <p class="message">
                Haz clic en el botón de abajo para finalizar tu registro y establecer tu contraseña.
            </p>

            <div class="cta-container">
                <a href="{{ $url }}" class="cta-button">
                    Completar mi registro
                </a>
            </div>
        </div>

        <div class="footer">
            @include('emails.partials.app-footer')
        </div>
    </div>
</body>

</html>