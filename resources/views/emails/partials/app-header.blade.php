<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
  style="width:100%;border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;">
  <tr>
    <td bgcolor="#2b6cb0" align="center" valign="top"
      style="padding:0;margin:0;background-color:#2b6cb0;border-bottom:1px solid #cbd5e1;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="width:100%;border-collapse:collapse;">
        <tr>
          <td bgcolor="#2b6cb0" align="center"
            style="padding:28px 20px;background-color:#2b6cb0;background-image:linear-gradient(135deg,#1d4e89 0%,#2b6cb0 100%);text-align:center;">
            @include('emails.partials.logo-app', ['logoAppDataUri' => $logoAppDataUri ?? null])
            <h1
              style="margin:0;color:#ffffff;font-size:24px;font-weight:700;letter-spacing:0.2px;font-family:Arial,Helvetica,sans-serif;">
              {{ $title ?? config('app.name') }}</h1>
            <p style="margin:6px 0 0;color:#e2e8f0;font-size:13px;font-family:Arial,Helvetica,sans-serif;">
              {{ $subtitle ?? config('app.name') }}</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>