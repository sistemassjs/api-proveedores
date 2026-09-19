<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsAuthClientAppMail;
use App\Support\ClientApp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use BuildsAuthClientAppMail;
    use Queueable, SerializesModels;

    public $url;

    public $userName;

    public string $appKey;

    public function __construct($url, $userName = null, ?string $appKey = null)
    {
        $this->url = $url;
        $this->userName = $userName;
        $this->appKey = $appKey ?? ClientApp::key();
    }

    public function build()
    {
        ClientApp::setCurrent($this->appKey);

        return $this->brandAuthMail(
            $this->subject('Recuperación de contraseña - '.ClientApp::name())
                ->view('emails.password-reset')
        );
    }
}
