<?php

namespace App\Actions\Subscriptions;

use App\Models\BusinessType;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Plans\PlanCatalogBootstrapper;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ProvisionCompanySubscription
{
    public function __construct(
        protected PlanCatalogBootstrapper $planCatalogBootstrapper,
    ) {
    }

    public function handle(Company $company, string $planCode = 'basic', ?Carbon $startsAt = null): Subscription
    {
        $this->planCatalogBootstrapper->ensureDefaults();

        // Al registrarse, la empresa todavia no tiene business_type_id (lo
        // asigna el platform_super_admin al activarla) — se ancla siempre al
        // vertical "general" como punto de partida neutral; el plan se
        // realinea al vertical real en CompaniesPage::confirmTypeModal() via
        // RealignSubscriptionPlanForBusinessType.
        $generalBusinessTypeId = BusinessType::query()->where('code', 'general')->value('id');

        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('business_type_id', $generalBusinessTypeId)
            ->first();

        if (! $plan) {
            throw new InvalidArgumentException('El plan solicitado no existe en el catalogo.');
        }

        $existingSubscription = Subscription::query()
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->latest('starts_at')
            ->latest('id')
            ->first();

        if ($existingSubscription) {
            return $existingSubscription->fresh('plan');
        }

        $startsAt ??= now();

        return Subscription::query()->create([
            'company_id'       => $company->id,
            'plan_id'          => $plan->id,
            'status'           => 'pending',
            'starts_at'        => null,
            'trial_ends_at'    => null,
            'billing_snapshot' => [
                'plan_code'      => $plan->code,
                'billing_period' => $plan->billing_period,
                'base_price'     => $plan->base_price,
            ],
        ])->fresh('plan');
    }
}
