<?php

namespace App\Actions\Settings;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use App\Support\Settings\CompanySettingCatalog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateCompanySettings
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected CompanyPlanResolver $companyPlanResolver,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $settings, ?User $actor = null): void
    {
        DB::transaction(function () use ($company, $settings, $actor) {
            foreach (CompanySettingCatalog::definitions() as $definition) {
                $group = $definition['group'];
                $key = $definition['key'];

                if ($group === 'electronic_billing' && ! $this->companyPlanResolver->hasModule($company, 'electronic_billing')) {
                    if (array_key_exists($group, $settings) && array_key_exists($key, $settings[$group] ?? [])) {
                        throw new InvalidArgumentException('El plan actual no tiene habilitado el modulo de facturacion electronica.');
                    }

                    continue;
                }

                if (! array_key_exists($group, $settings) || ! array_key_exists($key, $settings[$group] ?? [])) {
                    continue;
                }

                $submittedValue = $this->normalizeSubmittedValue($definition['type'], $settings[$group][$key]);
                $beforeValue = $this->companySettings->get($company, $group, $key);

                if ($this->equivalent($definition['type'], $beforeValue, $submittedValue)) {
                    continue;
                }

                $existing = CompanySetting::query()
                    ->where('company_id', $company->id)
                    ->where('group_key', $group)
                    ->where('setting_key', $key)
                    ->first();

                $beforeModel = $existing ? clone $existing : null;
                $saved = $this->companySettings->set($company, $group, $key, $submittedValue);

                if ($beforeModel) {
                    $this->auditLogger->logUpdated($company, 'company_setting.updated', $beforeModel, $saved, $actor);
                } else {
                    $this->auditLogger->logCreated($company, 'company_setting.created', $saved, $actor);
                }
            }

            $this->syncCompanyCoreFields($company, $settings);
        });
    }

    protected function syncCompanyCoreFields(Company $company, array $settings): void
    {
        $general = $settings['general'] ?? [];
        $updates = [];

        if (array_key_exists('legal_name', $general)) {
            $legalName = $this->normalizeNullableString($general['legal_name']);

            if ($legalName !== null) {
                $updates['legal_name'] = $legalName;
            }
        }

        if (array_key_exists('display_name', $general)) {
            $displayName = $this->normalizeNullableString($general['display_name']);

            if ($displayName !== null) {
                $updates['display_name'] = $displayName;
            }
        }

        if (array_key_exists('tax_id', $general)) {
            $updates['tax_id'] = $this->normalizeNullableString($general['tax_id']);
        }

        if ($updates !== []) {
            $company->update($updates);
        }
    }

    protected function normalizeSubmittedValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'string' => $this->normalizeNullableString($value),
            'integer', 'decimal', 'boolean', 'json' => $value,
            default => $value,
        };
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function equivalent(string $type, mixed $before, mixed $after): bool
    {
        return match ($type) {
            'boolean' => (bool) $before === (bool) $after,
            'integer' => (int) $before === (int) $after,
            'decimal' => bccomp((string) ($before ?? '0'), (string) ($after ?? '0'), 4) === 0,
            'json' => $before === $after,
            default => $this->normalizeNullableString($before) === $this->normalizeNullableString($after),
        };
    }
}
