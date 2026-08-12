<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class WelcomeUserMail extends Mailable
{
    use Queueable;

    /**
     * Se envia de forma sincrona (no implementa ShouldQueue) a proposito:
     * contiene la contraseña en texto plano y encolarla la dejaria un rato
     * en la tabla `jobs` sin cifrar hasta que el worker la procese.
     */
    public function __construct(public User $user, public string $plainPassword)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Bienvenido a '.\App\Models\PlatformSetting::appName())
            ->view('emails.welcome-user');
    }
}
