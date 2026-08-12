<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class SubscriptionRenewalReminderMail extends Mailable
{
    use Queueable;

    /**
     * Se envia de forma sincrona (no implementa ShouldQueue), igual que los
     * demas avisos criticos de esta app: no debe depender de que el worker
     * de la cola este vivo justo ese dia.
     */
    public function __construct(public Subscription $subscription)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Hoy vence tu suscripcion de '.\App\Models\PlatformSetting::appName())
            ->view('emails.subscription-renewal-reminder');
    }
}
