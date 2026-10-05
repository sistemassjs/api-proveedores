<?php

namespace App\Mail\Cotizacion;

use App\Models\SolicitudCotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CotizacionRespuestaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SolicitudCotizacion $solicitud,
        public string $mensaje,
        public ?string $pdfPath = null
    ) {
        $this->solicitud->loadMissing(['proveedor', 'detalles']);
    }

    public function envelope(): Envelope
    {
        $empresa = trim((string) (
            $this->solicitud->proveedor?->nombre_comercial
            ?: $this->solicitud->proveedor?->razon_social
            ?: 'Empresa'
        ));

        return new Envelope(
            subject: 'Cotización '.$this->solicitud->folio.' — '.$empresa,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cotizacion.respuesta-cliente',
            with: [
                'solicitud' => $this->solicitud,
                'mensaje' => $this->mensaje,
                'empresa' => trim((string) (
                    $this->solicitud->proveedor?->nombre_comercial
                    ?: $this->solicitud->proveedor?->razon_social
                    ?: 'la empresa'
                )),
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->pdfPath || ! Storage::disk('private')->exists($this->pdfPath)) {
            return [];
        }

        return [
            Attachment::fromPath(Storage::disk('private')->path($this->pdfPath))
                ->as('Cotizacion_'.$this->solicitud->folio.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
