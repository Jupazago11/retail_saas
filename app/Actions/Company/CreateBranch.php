<?php

namespace App\Actions\Company;

use App\Enums\RecordStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\CompanyStructureLimitGuard;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateBranch
{
    public function __construct(
        protected CompanyStructureLimitGuard $companyStructureLimitGuard,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $attributes, ?User $actor = null): Branch
    {
        $this->companyStructureLimitGuard->ensureCanCreateBranch($company);

        $name = trim((string) ($attributes['name'] ?? ''));
        $code = $this->normalizeCode($attributes['code'] ?? null);

        if ($name === '') {
            throw new InvalidArgumentException('La sucursal debe tener un nombre.');
        }

        if ($code === '') {
            throw new InvalidArgumentException('La sucursal debe tener un codigo.');
        }

        if (Branch::query()->where('company_id', $company->id)->where('code', $code)->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('Ya existe una sucursal activa con ese codigo en la empresa.');
        }

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'status' => RecordStatus::Active->value,
            'is_primary' => false,
        ]);

        $this->auditLogger->logCreated($company, 'branch.created', $branch, $actor);

        return $branch;
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
