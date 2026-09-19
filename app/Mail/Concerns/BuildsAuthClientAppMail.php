<?php

namespace App\Mail\Concerns;

use App\Support\ClientApp;
use App\Support\EmailLogoHelper;
use Illuminate\Mail\Mailable;

/**
 * Branding de correos de auth según X-Client-App / appKey (GestionPlus | NexProv).
 */
trait BuildsAuthClientAppMail
{
    protected function brandAuthMail(Mailable $mailable): Mailable
    {
        ClientApp::setCurrent($this->appKey);

        $name = ClientApp::name();
        $logoDataUri = EmailLogoHelper::logoClientAppDataUri($this->appKey);
        $mailTheme = ClientApp::mailTheme($this->appKey);

        return $mailable
            ->from(
                (string) config('mail.from.address'),
                $name
            )
            ->with([
                'clientAppName' => $name,
                'logoAppDataUri' => $logoDataUri,
                'appKey' => $this->appKey,
                'mailTheme' => $mailTheme,
            ]);
    }
}
