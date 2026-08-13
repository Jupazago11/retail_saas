<?php

namespace App\Actions\Plans;

use App\Models\Company;
use App\Models\CompanyLimitOverride;
use App\Models\PlanLimit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use InvalidArgumentException;

class ApplyCompanyLimitOverride
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected ReconcileCompanyStructureLimits $reconcileCompanyStructureLimits,
    ) {
    }

    public function handle(Company $company, ?CompanyLimitOverride $override, array $attributes, ?User $actor = null): CompanyLimitOverride
    {
        $limitKey = trim((string) ($attributes['limit_key'] ?? ''));
        $limitValue = (int) ($attributes['limit_value'] ?? 0);
        $startsAt = $this->blankToNull($attributes['starts_at'] ?? null);
        $endsAt = $this->blankToNull($attributes['ends_at'] ?? null);

        if ($limitKey === '') {
            throw new InvalidArgumentException('Debes seleccionar un limite valido.');
        }

        if ($limitValue <= 0) {
            throw new InvalidArgumentException('El valor del limite debe ser mayor a cero.');
        }

        if (! PlanLimit::query()->where('limit_key', $limitKey)->exists()) {
            throw new InvalidArgumentException('La clave de limite no existe en el catalogo.');
        }

        if ($startsAt && $endsAt && $endsAt < $startsAt) {
            throw new InvalidArgumentException('La fecha fin no puede ser anterior a la fecha inicio.');
        }

        if ($override && (int) $override->company_id !== (int) $company->id) {
            throw new InvalidArgumentException('El override de limite no pertenece a la empresa activa.');
        }

        if ($override) {
            $before = clone $override;

            $override->update([
                'limit_key' => $limitKey,
                'limit_value' => $limitValue,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->auditLogger->logUpdated($company, 'company_limit_override.updated', $before, $override->fresh(), $actor);

            $this->reconcileCompanyStructureLimits->handle($company, $actor);

            return $override->fresh();
        }

        $created = CompanyLimitOverride::query()->create([
            'company_id' => $company->id,
            'limit_key' => $limitKey,
            'limit_value' => $limitValue,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->auditLogger->logCreated($company, 'company_limit_override.created', $created->fresh(), $actor);

        $this->reconcileCompanyStructureLimits->handle($company, $actor);

        return $created->fresh();
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
