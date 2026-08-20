<?php

namespace App\Actions\Plans;

use App\Models\Company;
use App\Models\CompanyModuleOverride;
use App\Models\Module;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use InvalidArgumentException;

class ApplyCompanyModuleOverride
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected ReconcileCompanyInventoryTracking $reconcileCompanyInventoryTracking,
    ) {
    }

    public function handle(Company $company, ?CompanyModuleOverride $override, array $attributes, ?User $actor = null): CompanyModuleOverride
    {
        $moduleId = (int) ($attributes['module_id'] ?? 0);
        $enabled = (bool) ($attributes['enabled'] ?? false);
        $startsAt = $this->blankToNull($attributes['starts_at'] ?? null);
        $endsAt = $this->blankToNull($attributes['ends_at'] ?? null);

        $module = Module::query()->find($moduleId);

        if (! $module) {
            throw new InvalidArgumentException('Debes seleccionar un modulo valido.');
        }

        if ($startsAt && $endsAt && $endsAt < $startsAt) {
            throw new InvalidArgumentException('La fecha fin no puede ser anterior a la fecha inicio.');
        }

        if ($override && (int) $override->company_id !== (int) $company->id) {
            throw new InvalidArgumentException('El override de modulo no pertenece a la empresa activa.');
        }

        if ($override) {
            $before = clone $override;

            $override->update([
                'module_id' => $module->id,
                'enabled' => $enabled,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->auditLogger->logUpdated($company, 'company_module_override.updated', $before, $override->fresh('module'), $actor);

            $this->reconcileCompanyInventoryTracking->handle($company, $actor);

            return $override->fresh('module');
        }

        $created = CompanyModuleOverride::query()->create([
            'company_id' => $company->id,
            'module_id' => $module->id,
            'enabled' => $enabled,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->auditLogger->logCreated($company, 'company_module_override.created', $created->fresh('module'), $actor);

        $this->reconcileCompanyInventoryTracking->handle($company, $actor);

        return $created->fresh('module');
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
