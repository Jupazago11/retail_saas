<?php

namespace App\Actions\Subscriptions;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class RenewCompanySubscription
{
    public function __construct(
        protected ChangeCompanySubscription $changeCompanySubscription,
    ) {
    }

    public function handle(
        Company $company,
        Subscription $subscription,
        string $status = 'active',
        ?Carbon $startsAt = null,
        ?int $trialDays = null,
        ?User $actor = null,
    ): Subscription {
        if ((int) $subscription->company_id !== (int) $company->id || $subscription->bundle_id !== null) {
            throw new InvalidArgumentException('La suscripcion no pertenece a la empresa activa.');
        }

        if (! $subscription->plan_id) {
            throw new InvalidArgumentException('La suscripcion no tiene un plan valido para renovar.');
        }

        return $this->changeCompanySubscription->handle($company, [
            'plan_id' => (int) $subscription->plan_id,
            'status' => $status,
            'starts_at' => $startsAt ?? now(),
            'trial_days' => $trialDays,
        ], $actor);
    }
}
