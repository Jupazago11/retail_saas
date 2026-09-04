<?php

namespace App\Actions\Subscriptions;

use App\Models\BusinessType;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use InvalidArgumentException;

class RealignSubscriptionPlanForBusinessType
{
    /**
     * Si la suscripcion directa vigente de la empresa apunta a un plan de OTRO
     * vertical, la reasigna al plan del mismo "tier" (mismo code) dentro del
     * vertical nuevo. No toca suscripciones en bundle (se gestionan aparte).
     */
    public function handle(Company $company, BusinessType $businessType): void
    {
        $subscription = Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->latest('id')
            ->first();

        if (! $subscription || ! $subscription->plan) {
            return;
        }

        if ((int) $subscription->plan->business_type_id === (int) $businessType->id) {
            return;
        }

        $matchingPlan = Plan::query()
            ->where('business_type_id', $businessType->id)
            ->where('code', $subscription->plan->code)
            ->first();

        if (! $matchingPlan) {
            throw new InvalidArgumentException(
                "El vertical \"{$businessType->name}\" todavia no tiene un plan \"{$subscription->plan->code}\" en su catalogo."
            );
        }

        $subscription->update([
            'plan_id' => $matchingPlan->id,
            'billing_snapshot' => [
                'plan_code' => $matchingPlan->code,
                'billing_period' => $matchingPlan->billing_period,
                'base_price' => $matchingPlan->base_price,
            ],
        ]);
    }
}
