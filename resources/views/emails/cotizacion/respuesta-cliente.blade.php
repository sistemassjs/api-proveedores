<x-mail::message>
# Cotización {{ $solicitud->folio }}

Hola {{ $solicitud->cliente_nombre }},

{{ $empresa }} te envía la cotización solicitada.

@if(filled($mensaje))
{{ $mensaje }}
@endif

**Total:** ${{ number_format((float) $solicitud->total, 2) }}

Adjunto encontrarás el PDF con el detalle.

Gracias,<br>
{{ $empresa }} · NexProv
</x-mail::message>
