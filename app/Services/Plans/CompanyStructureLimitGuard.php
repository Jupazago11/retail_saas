<?php

namespace App\Services\Plans;

use App\Models\Company;
use InvalidArgumentException;

class CompanyStructureLimitGuard
{
    public function __construct(
        protected CompanyPlanResolver $companyPlanResolver,
    ) {
    }

    public function ensureCanCreateBranch(Company $company): void
    {
        $this->assertLimitNotReached(
            $company,
            'max_branches',
            $company->branches()->whereNull('deleted_at')->count(),
            'sucursal(es)',
        );
    }

    public function ensureCanCreateWarehouse(Company $company): void
    {
        $this->assertLimitNotReached(
            $company,
            'max_warehouses',
            $company->warehouses()->whereNull('deleted_at')->count(),
            'bodega(s)',
        );
    }

    public function ensureCanCreateCashRegister(Company $company): void
    {
        $this->assertLimitNotReached(
            $company,
            'max_cash_registers',
            $company->cashRegisters()->whereNull('deleted_at')->count(),
            'caja(s)',
        );
    }

    protected function assertLimitNotReached(Company $company, string $limitKey, int $currentCount, string $label): void
    {
        $limit = $this->companyPlanResolver->limit($company, $limitKey);

        if ($limit === null) {
            return;
        }

        if ($currentCount >= $limit) {
            throw new InvalidArgumentException(sprintf(
                'El plan actual permite hasta %d %s para esta empresa. Debes ampliar el plan antes de crear otra.',
                $limit,
                $label,
            ));
        }
    }
}
