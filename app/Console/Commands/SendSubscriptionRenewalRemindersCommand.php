<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\SendSubscriptionRenewalReminders;
use Illuminate\Console\Command;

class SendSubscriptionRenewalRemindersCommand extends Command
{
    protected $signature = 'subscriptions:send-renewal-reminders';

    protected $description = 'Avisa a las empresas que hoy vence su suscripcion y envia un resumen al administrador de la plataforma';

    public function handle(SendSubscriptionRenewalReminders $sendSubscriptionRenewalReminders): int
    {
        $count = $sendSubscriptionRenewalReminders->handle();

        $this->info("Recordatorios enviados: {$count}");

        return self::SUCCESS;
    }
}
