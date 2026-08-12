<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// El recordatorio corre en la manana (hoy vence, todavia hay tiempo de pagar).
// El cierre real corre al final del dia para dejar el dia completo disponible
// para pagar antes de finalizar/renovar la suscripcion.
Schedule::command('subscriptions:send-renewal-reminders')->dailyAt('08:00');
Schedule::command('subscriptions:process-due')->dailyAt('23:55');
