<?php

namespace App\Mail;

use App\Support\ClientApp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CompletaRegistroUsuarioMail extends Mailable
{
    use Queueable, SerializesModels;

    public $url;
    public string $appKey;

    public function __construct($url, ?string $appKey = null)
    {
        $this->url = $url;
        $this->appKey = $appKey ?? ClientApp::key();
    }

    public function build()
    {
        ClientApp::setCurrent($this->appKey);

        return $this->subject('Completar tu registro en '.ClientApp::name())
            ->view('emails.registro-completar');
    }
}
