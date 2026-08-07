<?php

namespace App\Services\Settings;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Support\Settings\CompanySettingCatalog;
use InvalidArgumentException;

class CompanySettings
{
    public function get(Company|int $company, string $groupKey, string $settingKey): mixed
    {
        $definition = $this->definition($groupKey, $settingKey);
        $companyId = $this->companyId($company);

        $setting = CompanySetting::query()
            ->where('company_id', $companyId)
            ->where('group_key', $groupKey)
            ->where('setting_key', $settingKey)
            ->first();

        if (! $setting) {
            return $definition['default'];
        }

        return $this->extractValue($setting);
    }

    public function set(Company|int $company, string $groupKey, string $settingKey, mixed $value): CompanySetting
    {
        $definition = $this->definition($groupKey, $settingKey);
        $normalized = $this->normalizeValue($definition['type'], $value);
        $payload = [
            'value_type' => $definition['type'],
            'value_string' => null,
            'value_integer' => null,
            'value_decimal' => null,
            'value_boolean' => null,
            'value_json' => null,
        ];

        match ($definition['type']) {
            'string' => $payload['value_string'] = $normalized,
            'integer' => $payload['value_integer'] = $normalized,
            'decimal' => $payload['value_decimal'] = $normalized,
            'boolean' => $payload['value_boolean'] = $normalized,
            'json' => $payload['value_json'] = $normalized,
            default => throw new InvalidArgumentException('Tipo de configuracion no soportado.'),
        };

        CompanySetting::query()->updateOrCreate(
            [
                'company_id' => $this->companyId($company),
                'group_key' => $groupKey,
                'setting_key' => $settingKey,
            ],
            $payload
        );

        return CompanySetting::query()
            ->where('company_id', $this->companyId($company))
            ->where('group_key', $groupKey)
            ->where('setting_key', $settingKey)
            ->firstOrFail();
    }

    public function group(Company|int $company, string $groupKey): array
    {
        $companyId = $this->companyId($company);
        $definitions = collect(CompanySettingCatalog::definitions())
            ->filter(fn (array $definition) => $definition['group'] === $groupKey)
            ->keyBy(fn (array $definition) => $definition['key']);

        $stored = CompanySetting::query()
            ->where('company_id', $companyId)
            ->where('group_key', $groupKey)
            ->get()
            ->keyBy('setting_key');

        return $definitions
            ->map(function (array $definition, string $settingKey) use ($stored) {
                $setting = $stored->get($settingKey);

                return $setting ? $this->extractValue($setting) : $definition['default'];
            })
            ->all();
    }

    protected function extractValue(CompanySetting $setting): mixed
    {
        return match ($setting->value_type) {
            'string' => $setting->value_string,
            'integer' => $setting->value_integer,
            'decimal' => $setting->value_decimal,
            'boolean' => $setting->value_boolean,
            'json' => $setting->value_json,
            default => null,
        };
    }

    protected function normalizeValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'string' => $value === null ? null : trim((string) $value),
            'integer' => $this->normalizeInteger($value),
            'decimal' => $this->normalizeDecimal($value),
            'boolean' => $this->normalizeBoolean($value),
            'json' => $this->normalizeJson($value),
            default => throw new InvalidArgumentException('Tipo de configuracion no soportado.'),
        };
    }

    protected function normalizeInteger(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Valor entero invalido.');
        }

        return (int) $value;
    }

    protected function normalizeDecimal(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Valor decimal invalido.');
        }

        return bcadd($value, '0', 4);
    }

    protected function normalizeBoolean(mixed $value): bool
    {
        if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('Valor booleano invalido.');
        }

        return (bool) $value;
    }

    protected function normalizeJson(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Valor JSON invalido.');
        }

        return $value;
    }

    protected function definition(string $groupKey, string $settingKey): array
    {
        $definition = CompanySettingCatalog::definition($groupKey, $settingKey);

        if (! $definition) {
            throw new InvalidArgumentException('La configuracion solicitada no existe en el catalogo.');
        }

        return $definition;
    }

    protected function companyId(Company|int $company): int
    {
        return $company instanceof Company ? $company->id : $company;
    }
}
