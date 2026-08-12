<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewAccountRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Se envia de forma sincrona (no implementa ShouldQueue) para que
     * el aviso no dependa de que el worker de la cola este vivo: es
     * literalmente el proposito de este correo, y un worker caido no
     * debe dejarlo atrapado en la tabla `jobs` sin que nadie se entere.
     */
    public function __construct(public User $user, public string $planName)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Nueva cuenta registrada en '.\App\Models\PlatformSetting::appName())
            ->view('emails.new-account-admin');
    }
}
