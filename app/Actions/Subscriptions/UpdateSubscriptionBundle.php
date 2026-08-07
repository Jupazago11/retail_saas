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

class UpdateSubscriptionBundle
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, SubscriptionBundleCompany $membership, array $attributes, ?User $actor = null): SubscriptionBundleCompany
    {
        if ((int) $membership->company_id !== (int) $company->id) {
            throw new InvalidArgumentException('El membership no pertenece a la empresa activa.');
        }

        $bundle = $membership->bundle()->firstOrFail();
        $subscription = Subscription::query()
            ->where('bundle_id', $bundle->id)
            ->latest('starts_at')
            ->latest('id')
            ->first();

        if (! $subscription) {
            throw new InvalidArgumentException('El bundle no tiene una suscripcion base para actualizar.');
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
            ->find($planId);

        if (! $plan) {
            throw new InvalidArgumentException('Debes seleccionar un plan activo para el bundle.');
        }

        return DB::transaction(function () use ($actor, $bundle, $company, $discountType, $discountValue, $maxCompanies, $membership, $name, $plan, $status, $subscription) {
            $beforeBundle = $bundle->fresh();
            $beforeSubscription = $subscription->fresh();
            $beforeMembership = $membership->fresh();

            $bundle->update([
                'name' => $name,
                'status' => $status,
                'max_companies' => $maxCompanies,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
            ]);

            $subscription->update([
                'plan_id' => $plan->id,
                'status' => $status === RecordStatus::Active->value ? 'active' : 'ended',
                'ends_at' => $status === RecordStatus::Inactive->value ? now() : null,
                'billing_snapshot' => [
                    'plan_code' => $plan->code,
                    'billing_period' => $plan->billing_period,
                    'base_price' => $plan->base_price,
                ],
            ]);

            $membership->update([
                'plan_id' => $plan->id,
            ]);

            $this->auditLogger->logSnapshot(
                $company,
                'subscription_bundle.updated',
                $bundle::class,
                $bundle->getKey(),
                $this->snapshot($beforeBundle, $beforeSubscription, $beforeMembership),
                $this->snapshot($bundle->fresh(), $subscription->fresh(), $membership->fresh()),
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
