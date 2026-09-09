<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ValidaCorreoProveedorBasicoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $url;

    public $nombreEmpresa;

    public function __construct($url, $nombreEmpresa = null)
    {
        $this->url = $url;
        $this->nombreEmpresa = $nombreEmpresa;
    }

    public function build()
    {
        return $this->subject('Bienvenido a '.config('app.name').' - Crea tu contraseña')
            ->view('emails.valida-correo-proveedor-basico');
    }
}
