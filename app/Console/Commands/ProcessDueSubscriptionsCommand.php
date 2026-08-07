<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\ProcessDueSubscriptions;
use Illuminate\Console\Command;

class ProcessDueSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:process-due';

    protected $description = 'Finaliza suscripciones vencidas y renueva automaticamente las empresas con auto_renew activo';

    public function handle(ProcessDueSubscriptions $processDueSubscriptions): int
    {
        $processed = $processDueSubscriptions->handle();

        $this->info("Suscripciones procesadas: {$processed}");

        return self::SUCCESS;
    }
}
