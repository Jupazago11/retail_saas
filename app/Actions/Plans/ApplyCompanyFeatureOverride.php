<?php

namespace App\Actions\Plans;

use App\Models\Company;
use App\Models\CompanyFeatureOverride;
use App\Models\Feature;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use InvalidArgumentException;

class ApplyCompanyFeatureOverride
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, ?CompanyFeatureOverride $override, array $attributes, ?User $actor = null): CompanyFeatureOverride
    {
        $featureId = (int) ($attributes['feature_id'] ?? 0);
        $enabled = (bool) ($attributes['enabled'] ?? false);
        $startsAt = $this->blankToNull($attributes['starts_at'] ?? null);
        $endsAt = $this->blankToNull($attributes['ends_at'] ?? null);

        $feature = Feature::query()->find($featureId);

        if (! $feature) {
            throw new InvalidArgumentException('Debes seleccionar una feature valida.');
        }

        if ($startsAt && $endsAt && $endsAt < $startsAt) {
            throw new InvalidArgumentException('La fecha fin no puede ser anterior a la fecha inicio.');
        }

        if ($override && (int) $override->company_id !== (int) $company->id) {
            throw new InvalidArgumentException('El override de feature no pertenece a la empresa activa.');
        }

        if ($override) {
            $before = clone $override;

            $override->update([
                'feature_id' => $feature->id,
                'enabled' => $enabled,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->auditLogger->logUpdated($company, 'company_feature_override.updated', $before, $override->fresh(['feature.module']), $actor);

            return $override->fresh(['feature.module']);
        }

        $created = CompanyFeatureOverride::query()->create([
            'company_id' => $company->id,
            'feature_id' => $feature->id,
            'enabled' => $enabled,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->auditLogger->logCreated($company, 'company_feature_override.created', $created->fresh(['feature.module']), $actor);

        return $created->fresh(['feature.module']);
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
