@php
    $theme = $mailTheme ?? \App\Support\ClientApp::mailTheme($appKey ?? null);
    $link = $theme['link'] ?? '#93c5fd';
@endphp
<div style="background-color:#111111;padding:22px 18px;text-align:center;border-top:1px solid #2a2a2a;">
  <p style="color:#e5e7eb;font-size:12px;margin:0 0 6px;">
    © {{ date('Y') }} {{ $clientAppName ?? config('app.name') }}. Todos los derechos reservados.
  </p>
  <p style="color:#d1d5db;font-size:12px;margin:0;">
    ¿Necesitas ayuda? <a href="mailto:{{ config('mail.from.address') }}" style="color:{{ $link }};text-decoration:none;">Contáctanos</a>
  </p>
</div>
