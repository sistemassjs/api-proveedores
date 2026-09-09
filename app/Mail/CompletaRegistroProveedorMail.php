<?php

namespace App\Mail;

use App\Models\Proveedor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CompletaRegistroProveedorMail extends Mailable
{
    use Queueable, SerializesModels;

    public $url;
    public $proveedor;

    public function __construct(String $url, Proveedor $proveedor)
    {
        $this->url = $url;
        $this->proveedor = $proveedor;
    }

    public function build()
    {
        return $this->subject('Completar tu registro en la aplicación')
            ->view('emails.registro-completar');
    }
}
