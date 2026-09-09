<?php

namespace App\Mail;

use App\Models\Presupuesto;
use App\Support\PresupuestoPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PresupuestoEnviadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?string $proveedorLogoBase64 = null;

    public function __construct(
        public Presupuesto $presupuesto,    
        public string $enlacePublico,
        public string $nombreReceptor,
        public bool $incluirInvitacion = false
    ) {
        $this->proveedorLogoBase64 = $this->resolverLogoProveedorBase64();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Presupuesto ' . $this->presupuesto->numero_presupuesto . ' - ' . ($this->presupuesto->proveedor?->nombre_comercial ?? $this->presupuesto->proveedor?->razon_social ?? 'Proveedor'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.presupuesto.enviado',
            with: [
                'proveedorLogo' => $this->proveedorLogoBase64,
            ],
        );
    }

    public function attachments(): array
    {
        $filename = 'Presupuesto_' . ($this->presupuesto->numero_presupuesto ?: $this->presupuesto->id) . '.pdf';

        return [
            Attachment::fromData(
                fn () => PresupuestoPdf::renderPdfBinary($this->presupuesto),
                $filename
            )->withMime('application/pdf'),
        ];
    }

    private function resolverLogoProveedorBase64(): ?string
    {
        $logo = $this->presupuesto->proveedor?->logo;
        if (! is_string($logo) || trim($logo) === '') {
            return null;
        }

        if (str_starts_with($logo, 'data:image')) {
            return $logo;
        }

        if (filter_var($logo, FILTER_VALIDATE_URL)) {
            return null;
        }

        $logoPath = null;
        if (str_starts_with($logo, '/') || str_starts_with($logo, 'storage/')) {
            $logoPath = public_path($logo);
        } elseif (Storage::disk('public')->exists($logo)) {
            $logoPath = Storage::disk('public')->path($logo);
        } else {
            $logoPath = public_path('storage/' . $logo);
        }

        if (! $logoPath || ! is_readable($logoPath)) {
            return null;
        }

        $binary = @file_get_contents($logoPath);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }
}
