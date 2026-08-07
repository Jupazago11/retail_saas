<?php

namespace App\Actions\Company;

use App\Enums\RecordStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\CompanyStructureLimitGuard;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateCashRegister
{
    public function __construct(
        protected CompanyStructureLimitGuard $companyStructureLimitGuard,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $attributes, ?User $actor = null): CashRegister
    {
        $this->companyStructureLimitGuard->ensureCanCreateCashRegister($company);

        $branch = Branch::query()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->findOrFail((int) ($attributes['branch_id'] ?? 0));

        $name = trim((string) ($attributes['name'] ?? ''));
        $code = $this->normalizeCode($attributes['code'] ?? null);

        if ($name === '') {
            throw new InvalidArgumentException('La caja debe tener un nombre.');
        }

        if ($code === '') {
            throw new InvalidArgumentException('La caja debe tener un codigo.');
        }

        if (CashRegister::query()->where('company_id', $company->id)->where('code', $code)->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('Ya existe una caja activa con ese codigo en la empresa.');
        }

        $cashRegister = CashRegister::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => $name,
            'code' => $code,
            'status' => RecordStatus::Active->value,
            'is_primary' => false,
        ]);

        $this->auditLogger->logCreated($company, 'cash_register.created', $cashRegister, $actor);

        return $cashRegister;
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
