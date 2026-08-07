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
        $plan = Plan::query()->where('code', $planCode)->first();

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
