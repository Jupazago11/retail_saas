<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

class SubscriptionsDueTodayMail extends Mailable
{
    use Queueable;

    /**
     * Se envia de forma sincrona por la misma razon que el resto de avisos
     * criticos de esta app: no debe depender del worker de la cola.
     */
    public function __construct(public Collection $subscriptions)
    {
    }

    public function build(): self
    {
        $count = $this->subscriptions->count();

        return $this
            ->subject($count.' '.\Illuminate\Support\Str::plural('suscripcion', $count).' '.($count === 1 ? 'vence' : 'vencen').' hoy en '.\App\Models\PlatformSetting::appName())
            ->view('emails.subscriptions-due-today');
    }
}
