<?php

namespace App\Mail\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Envuelve una notificación existente y fuerza el canal `mail` únicamente,
 * evitando broadcast, FCM, base de datos y otros canales definidos en la clase interna.
 */
class MailOnlyNotification extends Notification
{
    use Queueable;

    /**
     * @param  Notification&object{toMail(object): MailMessage}  $inner
     */
    public function __construct(
        private readonly Notification $inner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! method_exists($this->inner, 'toMail')) {
            throw new \RuntimeException('La notificación interna no implementa toMail().');
        }

        /** @var Notification&object{toMail(object): MailMessage} $inner */
        $inner = $this->inner;

        return $inner->toMail($notifiable);
    }
}
