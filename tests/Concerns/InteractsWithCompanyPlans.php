<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use InvalidArgumentException;

trait InteractsWithCompanyPlans
{
    protected function assignCompanyPlan(Company $company, string $planCode): Subscription
    {
        // Desde que plans.code dejo de ser unico global (unique compuesto
        // business_type_id+code, ver docs/decisiones-tecnicas.md "Planes
        // independientes por vertical de negocio"), hay que anclar el
        // vertical de la empresa o esta consulta queda ambigua entre, por
        // ejemplo, el "basic" de general y el "basic" de restaurant.
        $plan = Plan::query()
            ->where('code', $planCode)
            ->when($company->business_type_id, fn ($query) => $query->where('business_type_id', $company->business_type_id))
            ->first();

        if (! $plan) {
            throw new InvalidArgumentException('El plan solicitado no existe para pruebas.');
        }

        $subscription = Subscription::query()
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->latest('id')
            ->firstOrFail();

        $subscription->update([
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'trial_ends_at' => null,
        ]);

        return $subscription->fresh('plan');
    }
}
