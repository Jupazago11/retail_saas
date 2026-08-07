<?php

namespace App\Actions\Company;

use App\Enums\RecordStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\CompanyStructureLimitGuard;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateWarehouse
{
    public function __construct(
        protected CompanyStructureLimitGuard $companyStructureLimitGuard,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $attributes, ?User $actor = null): Warehouse
    {
        $this->companyStructureLimitGuard->ensureCanCreateWarehouse($company);

        $branch = Branch::query()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->findOrFail((int) ($attributes['branch_id'] ?? 0));

        $name = trim((string) ($attributes['name'] ?? ''));
        $code = $this->normalizeCode($attributes['code'] ?? null);

        if ($name === '') {
            throw new InvalidArgumentException('La bodega debe tener un nombre.');
        }

        if ($code === '') {
            throw new InvalidArgumentException('La bodega debe tener un codigo.');
        }

        if (Warehouse::query()->where('company_id', $company->id)->where('code', $code)->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('Ya existe una bodega activa con ese codigo en la empresa.');
        }

        $warehouse = Warehouse::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => $name,
            'code' => $code,
            'status' => RecordStatus::Active->value,
            'is_primary' => false,
        ]);

        $this->auditLogger->logCreated($company, 'warehouse.created', $warehouse, $actor);

        return $warehouse;
    }

    protected function normalizeCode(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return Str::upper(Str::slug($value, '_'));
    }
}
