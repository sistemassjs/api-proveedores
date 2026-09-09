<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $nombre;
    public $email;
    public $telefono;
    public $empresa;
    public $mensaje;
    public $files;

    public function __construct(
        string $nombre = '',
        string $email = '',
        string $telefono    = '',
        string $empresa = '',
        string $mensaje = '',
        array $files = []
    ) {
        $this->files = $files;
        $this->nombre = $nombre;
        $this->email = $email;
        $this->telefono = $telefono;
        $this->empresa = $empresa;
        $this->mensaje = $mensaje;
    }

    public function build()
    {
        $email = $this->subject('Nuevo mensaje de contacto')
            ->view('emails.contacto');

        foreach ($this->files as $file) {
            $email->attach(
                $file->getRealPath(),
                [
                    'as' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                ]
            );
        }

        return $email;
    }
}
