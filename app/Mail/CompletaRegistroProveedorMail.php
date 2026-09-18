<?php

namespace App\Mail;

use App\Models\Proveedor;
use App\Support\ClientApp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CompletaRegistroProveedorMail extends Mailable
{
    use Queueable, SerializesModels;

    public $url;
    public $proveedor;
    public string $appKey;

    public function __construct(String $url, Proveedor $proveedor, ?string $appKey = null)
    {
        $this->url = $url;
        $this->proveedor = $proveedor;
        $this->appKey = $appKey ?? ClientApp::key();
    }

    public function build()
    {
        ClientApp::setCurrent($this->appKey);

        return $this->subject('Completar tu registro en '.ClientApp::name())
            ->view('emails.registro-completar');
    }
}
