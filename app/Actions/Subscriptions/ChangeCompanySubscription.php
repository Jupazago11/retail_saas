<?php

namespace App\Actions\Subscriptions;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\PlanCatalogBootstrapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChangeCompanySubscription
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected PlanCatalogBootstrapper $planCatalogBootstrapper,
    ) {
    }

    public function handle(Company $company, array $attributes, ?User $actor = null): Subscription
    {
        $this->planCatalogBootstrapper->ensureDefaults();

        $planId = (int) ($attributes['plan_id'] ?? 0);
        $status = (string) ($attributes['status'] ?? 'active');
        $startsAt = $attributes['starts_at'] ?? now();
        $trialDays = max(1, (int) ($attributes['trial_days'] ?? 14));
        $endsAt = $attributes['ends_at'] ?? null;

        if (! in_array($status, ['active', 'trialing'], true)) {
            throw new InvalidArgumentException('El estado de la suscripcion no es valido.');
        }

        $plan = Plan::query()
            ->where('status', RecordStatus::Active->value)
            ->find($planId);

        if (! $plan) {
            throw new InvalidArgumentException('Debes seleccionar un plan activo.');
        }

        $startsAt = $startsAt instanceof Carbon ? $startsAt : Carbon::parse((string) $startsAt);
        $endsAt = match (true) {
            $endsAt === null => null,
            $endsAt instanceof Carbon => $endsAt,
            default => Carbon::parse((string) $endsAt),
        };

        return DB::transaction(function () use ($company, $plan, $status, $startsAt, $endsAt, $trialDays, $actor) {
            $activeDirectSubscription = Subscription::query()
                ->with('plan')
                ->where('company_id', $company->id)
                ->whereNull('bundle_id')
                ->activeAt($startsAt)
                ->latest('starts_at')
                ->latest('id')
                ->first();

            if (
                $activeDirectSubscription
                && (int) $activeDirectSubscription->plan_id === (int) $plan->id
                && $activeDirectSubscription->status === $status
            ) {
                throw new InvalidArgumentException('La empresa ya tiene ese plan directo activo.');
            }

            Subscription::query()
                ->where('company_id', $company->id)
                ->whereNull('bundle_id')
                ->where(function ($query) use ($startsAt) {
                    $query
                        ->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $startsAt);
                })
                ->orderBy('id')
                ->get()
                ->each(function (Subscription $subscription) use ($company, $startsAt, $actor) {
                    $before = $subscription->replicate()->forceFill([
                        'id' => $subscription->id,
                        'created_at' => $subscription->created_at,
                        'updated_at' => $subscription->updated_at,
                    ]);

                    $subscription->update([
                        'status' => 'ended',
                        'ends_at' => $startsAt->copy()->subSecond(),
                        'trial_ends_at' => $subscription->trial_ends_at && $subscription->trial_ends_at->greaterThan($startsAt)
                            ? $startsAt->copy()->subSecond()
                            : $subscription->trial_ends_at,
                    ]);

                    $this->auditLogger->logUpdated($company, 'subscription.ended', $before, $subscription->fresh(), $actor);
                });

            $subscription = Subscription::query()->create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trial_ends_at' => $status === 'trialing'
                    ? $startsAt->copy()->addDays($trialDays)
                    : null,
                'billing_snapshot' => [
                    'plan_code' => $plan->code,
                    'billing_period' => $plan->billing_period,
                    'base_price' => $plan->base_price,
                ],
            ]);

            $this->auditLogger->logCreated($company, 'subscription.created', $subscription, $actor);

            return $subscription->fresh('plan');
        });
    }
}
