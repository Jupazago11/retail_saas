<?php

namespace App\Actions\Subscriptions;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class EndCompanySubscription
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, Subscription $subscription, ?Carbon $endedAt = null, ?User $actor = null): Subscription
    {
        if ((int) $subscription->company_id !== (int) $company->id || $subscription->bundle_id !== null) {
            throw new InvalidArgumentException('La suscripcion no pertenece a la empresa activa.');
        }

        if ($subscription->status === 'ended') {
            throw new InvalidArgumentException('La suscripcion ya estaba finalizada.');
        }

        $endedAt ??= now();

        if ($subscription->starts_at && $endedAt->lt($subscription->starts_at)) {
            throw new InvalidArgumentException('La fecha de cierre no puede ser anterior al inicio de la suscripcion.');
        }

        $before = $subscription->replicate()->forceFill([
            'id' => $subscription->id,
            'created_at' => $subscription->created_at,
            'updated_at' => $subscription->updated_at,
        ]);

        $subscription->update([
            'status' => 'ended',
            'ends_at' => $endedAt,
            'trial_ends_at' => $subscription->trial_ends_at && $subscription->trial_ends_at->gt($endedAt)
                ? $endedAt
                : $subscription->trial_ends_at,
        ]);

        $this->auditLogger->logUpdated($company, 'subscription.ended', $before, $subscription->fresh(), $actor);

        return $subscription->fresh('plan');
    }
}
