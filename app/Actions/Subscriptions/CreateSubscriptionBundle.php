<?php

namespace App\Actions\Subscriptions;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionBundle;
use App\Models\SubscriptionBundleCompany;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateSubscriptionBundle
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $attributes, ?User $actor = null): SubscriptionBundleCompany
    {
        if ($company->subscriptionBundleMemberships()->exists()) {
            throw new InvalidArgumentException('La empresa activa ya pertenece a un bundle.');
        }

        $name = $this->blankToNull($attributes['name'] ?? null);
        $status = (string) ($attributes['status'] ?? RecordStatus::Active->value);
        $maxCompanies = $this->normalizeNullableInt($attributes['max_companies'] ?? null);
        $discountType = $this->blankToNull($attributes['discount_type'] ?? null);
        $discountValue = $this->normalizeDecimal($attributes['discount_value'] ?? 0);
        $planId = (int) ($attributes['plan_id'] ?? 0);

        if ($name === null) {
            throw new InvalidArgumentException('El bundle debe tener un nombre.');
        }

        if (! in_array($status, [RecordStatus::Active->value, RecordStatus::Inactive->value], true)) {
            throw new InvalidArgumentException('El estado del bundle no es valido.');
        }

        if ($discountType !== null && ! in_array($discountType, ['percentage', 'fixed_amount'], true)) {
            throw new InvalidArgumentException('El tipo de descuento del bundle no es valido.');
        }

        if ($discountType === null && bccomp($discountValue, '0.00', 2) > 0) {
            throw new InvalidArgumentException('Debes indicar el tipo de descuento cuando el valor es mayor a cero.');
        }

        $plan = Plan::query()
            ->where('status', RecordStatus::Active->value)
            ->when(
                $company->business_type_id,
                fn ($query) => $query->where('business_type_id', $company->business_type_id)
            )
            ->find($planId);

        if (! $plan) {
            throw new InvalidArgumentException('Debes seleccionar un plan activo del vertical de la empresa para el bundle.');
        }

        if (! $company->business_type_id) {
            $company->update(['business_type_id' => $plan->business_type_id]);
        }

        return DB::transaction(function () use ($actor, $company, $discountType, $discountValue, $maxCompanies, $name, $plan, $status) {
            $bundle = SubscriptionBundle::query()->create([
                'owner_user_id' => $company->owner_user_id,
                'name' => $name,
                'status' => $status,
                'max_companies' => $maxCompanies,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
            ]);

            $subscription = Subscription::query()->create([
                'bundle_id' => $bundle->id,
                'plan_id' => $plan->id,
                'status' => $status === RecordStatus::Active->value ? 'active' : 'ended',
                'starts_at' => now(),
                'ends_at' => $status === RecordStatus::Inactive->value ? now() : null,
                'billing_snapshot' => [
                    'plan_code' => $plan->code,
                    'billing_period' => $plan->billing_period,
                    'base_price' => $plan->base_price,
                ],
            ]);

            $membership = SubscriptionBundleCompany::query()->create([
                'bundle_id' => $bundle->id,
                'company_id' => $company->id,
                'plan_id' => $plan->id,
            ]);

            $this->auditLogger->logSnapshot(
                $company,
                'subscription_bundle.created',
                $bundle::class,
                $bundle->getKey(),
                null,
                $this->snapshot($bundle->fresh(), $subscription, $membership),
                $actor,
            );

            return $membership->fresh(['plan', 'bundle.owner', 'bundle.subscriptions.plan', 'bundle.companies.company']);
        });
    }

    protected function snapshot(SubscriptionBundle $bundle, Subscription $subscription, SubscriptionBundleCompany $membership): array
    {
        return [
            ...$bundle->withoutRelations()->attributesToArray(),
            'subscription_plan_id' => (int) $subscription->plan_id,
            'subscription_status' => $subscription->status,
            'company_ids' => [$membership->company_id],
            'membership_plan_ids' => [$membership->plan_id],
        ];
    }

    protected function normalizeDecimal(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '0.00';
        }

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Decimal invalido.');
        }

        return bcadd($value, '0', 2);
    }

    protected function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
