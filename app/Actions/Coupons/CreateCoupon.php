<?php

namespace App\Actions\Coupons;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\SubscriptionBundle;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateCoupon
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $attributes, ?User $actor = null): Coupon
    {
        $code = $this->normalizeCode($attributes['code'] ?? null);
        $name = $this->blankToNull($attributes['name'] ?? null);
        $discountType = (string) ($attributes['discount_type'] ?? '');
        $discountValue = $this->normalizeDecimal($attributes['discount_value'] ?? null);
        $status = (string) ($attributes['status'] ?? RecordStatus::Active->value);
        $planIds = $this->normalizeIdArray($attributes['plan_ids'] ?? []);
        $bundleIds = $this->normalizeIdArray($attributes['bundle_ids'] ?? []);

        if ($code === '') {
            throw new InvalidArgumentException('El cupon debe tener un codigo.');
        }

        if ($name === null) {
            throw new InvalidArgumentException('El cupon debe tener un nombre.');
        }

        if (! in_array($discountType, ['percentage', 'fixed_amount'], true)) {
            throw new InvalidArgumentException('El tipo de descuento del cupon no es valido.');
        }

        if (bccomp($discountValue, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El valor del cupon debe ser mayor a cero.');
        }

        if (! in_array($status, [RecordStatus::Active->value, RecordStatus::Inactive->value], true)) {
            throw new InvalidArgumentException('El estado del cupon no es valido.');
        }

        if (Coupon::query()->where('code', $code)->exists()) {
            throw new InvalidArgumentException('Ya existe un cupon con ese codigo.');
        }

        $this->assertTargetsExist($planIds, $bundleIds);

        return DB::transaction(function () use ($company, $actor, $attributes, $bundleIds, $code, $discountType, $discountValue, $name, $planIds, $status) {
            $coupon = Coupon::query()->create([
                'code' => $code,
                'name' => $name,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'starts_at' => $this->blankToNull($attributes['starts_at'] ?? null),
                'expires_at' => $this->blankToNull($attributes['expires_at'] ?? null),
                'total_uses_limit' => $this->normalizeNullableInt($attributes['total_uses_limit'] ?? null),
                'per_user_limit' => $this->normalizeNullableInt($attributes['per_user_limit'] ?? null),
                'per_company_limit' => $this->normalizeNullableInt($attributes['per_company_limit'] ?? null),
                'status' => $status,
            ]);

            $coupon->plans()->sync($planIds);
            $coupon->bundles()->sync($bundleIds);
            $coupon = $coupon->fresh(['plans', 'bundles']);

            $this->auditLogger->logSnapshot(
                $company,
                'coupon.created',
                $coupon::class,
                $coupon->getKey(),
                null,
                $this->snapshot($coupon),
                $actor,
            );

            return $coupon;
        });
    }

    protected function snapshot(Coupon $coupon): array
    {
        return [
            ...$coupon->withoutRelations()->attributesToArray(),
            'plan_ids' => $coupon->plans->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'bundle_ids' => $coupon->bundles->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    protected function assertTargetsExist(array $planIds, array $bundleIds): void
    {
        if (count($planIds) !== Plan::query()->where('status', RecordStatus::Active->value)->whereIn('id', $planIds)->count()) {
            throw new InvalidArgumentException('Uno o varios planes seleccionados no existen o estan inactivos.');
        }

        if (count($bundleIds) !== SubscriptionBundle::query()->where('status', RecordStatus::Active->value)->whereIn('id', $bundleIds)->count()) {
            throw new InvalidArgumentException('Uno o varios bundles seleccionados no existen o estan inactivos.');
        }
    }

    protected function normalizeCode(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : Str::upper($value);
    }

    protected function normalizeDecimal(mixed $value): string
    {
        $value = trim((string) $value);

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

    protected function normalizeIdArray(array $values): array
    {
        return collect($values)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
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
