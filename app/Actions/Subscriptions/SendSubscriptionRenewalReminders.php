<?php

namespace App\Actions\Subscriptions;

use App\Mail\SubscriptionRenewalReminderMail;
use App\Mail\SubscriptionsDueTodayMail;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionRenewalReminders
{
    public function handle(?Carbon $moment = null): int
    {
        $moment = $moment ?? now();

        $subscriptions = $this->dueToday($moment);

        foreach ($subscriptions as $subscription) {
            try {
                Mail::to($subscription->company->owner->email)
                    ->send(new SubscriptionRenewalReminderMail($subscription));
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el recordatorio de vencimiento al cliente.', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($subscriptions->isNotEmpty()) {
            try {
                Mail::to(PlatformSetting::ownerNotificationEmail())
                    ->send(new SubscriptionsDueTodayMail($subscriptions));
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el resumen de vencimientos al administrador.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $subscriptions->count();
    }

    protected function dueToday(Carbon $moment): Collection
    {
        return Subscription::query()
            ->with(['company.owner', 'plan'])
            ->whereNull('bundle_id')
            ->whereIn('status', ['active', 'trialing'])
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', $moment->toDateString())
            ->get()
            ->filter(fn (Subscription $subscription) => $subscription->company && $subscription->company->owner?->email)
            ->values();
    }
}
