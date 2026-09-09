<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Presupuesto recibido</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f6fb;">
  @php
    $emisor = $presupuesto->proveedor?->nombre_comercial ?? $presupuesto->proveedor?->razon_social ?? 'Un proveedor';
    $concepto = (string) ($presupuesto->concepto_general ?? '');
    $conceptoCorto = mb_strlen($concepto) > 80 ? mb_substr($concepto, 0, 80) . '...' : $concepto;
  @endphp

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f3f6fb;">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:600px;background-color:#ffffff;border:1px solid #e6ebf2;">
          <tr>
            <td style="padding:0;background-color:#ffffff;">
              @include('emails.partials.app-header', ['title' => 'Presupuesto recibido'])
            </td>
          </tr>
  
          <tr>
            <td style="padding:24px;font-family:Arial,Helvetica,sans-serif;color:#1f2937;font-size:16px;line-height:24px;">
              <p style="margin:0 0 12px 0;">Hola {{ $nombreReceptor }},</p>
              <p style="margin:0 0 18px 0;">
                {{ $emisor }} te ha enviado un presupuesto para tu revision.
              </p>

              @if(!empty($incluirInvitacion))
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:14px;border:1px solid #d6e4ff;background-color:#eef5ff;">
                  <tr>
                    <td style="padding:12px;font-size:14px;line-height:20px;color:#1e3a8a;">
                      <strong>Invitación a la app:</strong> Te invitamos a autorizar este presupuesto desde la app de Proveedores. También podrás gestionar de forma profesional tus siguientes presupuestos y solicitudes de pago.
                    </td>
                  </tr>
                </table>
              @endif

              @include('emails.partials.provider-card', ['proveedor' => $presupuesto->proveedor, 'proveedorLogo' => $proveedorLogo ?? null])
              @include('emails.partials.presupuesto-summary', ['presupuesto' => $presupuesto])

              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:18px;border:1px solid #d6e4ff;background-color:#eef5ff;">
                <tr>
                  <td style="padding:12px;font-size:14px;line-height:20px;color:#1e3a8a;">
                    Puedes ver, compartir, aceptar o rechazar este presupuesto desde el siguiente enlace seguro:
                  </td>
                </tr>
              </table>

              <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:20px auto 0 auto;">
                <tr>
                  <td align="center" bgcolor="#1e88e5" style="border-radius:4px;">
                    <a href="{{ $enlacePublico }}" target="_blank" style="display:inline-block;padding:12px 22px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:20px;font-weight:700;color:#ffffff;text-decoration:none;">
                      Ver presupuesto
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:22px 0 0 0;font-size:13px;line-height:18px;color:#64748b;">
                Este enlace es unico y seguro. Si tienes dudas, contacta directamente al proveedor.
              </p>
              <p style="margin:10px 0 0 0;font-size:13px;line-height:18px;color:#64748b;">
                Adjuntamos el PDF del presupuesto para tu archivo.
              </p>
            </td>
          </tr>

          <tr><td>@include('emails.partials.app-footer')</td></tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
