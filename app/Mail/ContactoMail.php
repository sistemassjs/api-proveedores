<?php

namespace App\Mail;

use App\Support\ClientApp;
use App\Support\EmailLogoHelper;
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

    public string $appKey;

    public function __construct(
        string $nombre = '',
        string $email = '',
        string $telefono = '',
        string $empresa = '',
        string $mensaje = '',
        array $files = [],
        ?string $appKey = null
    ) {
        $this->files = $files;
        $this->nombre = $nombre;
        $this->email = $email;
        $this->telefono = $telefono;
        $this->empresa = $empresa;
        $this->mensaje = $mensaje;
        $this->appKey = ClientApp::normalize($appKey ?? ClientApp::key());
    }

    public function build()
    {
        ClientApp::setCurrent($this->appKey);

        $appName = ClientApp::nameFor($this->appKey);
        $email = $this->subject('Nuevo mensaje de contacto — '.$appName)
            ->from((string) config('mail.from.address'), $appName)
            ->view('emails.contacto', [
                'clientAppName' => $appName,
                'logoAppDataUri' => EmailLogoHelper::logoClientAppDataUri($this->appKey),
                'appKey' => $this->appKey,
                'mailTheme' => ClientApp::mailTheme($this->appKey),
            ]);

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
