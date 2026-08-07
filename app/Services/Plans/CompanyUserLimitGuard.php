<?php

namespace App\Services\Plans;

use App\Enums\RecordStatus;
use App\Models\Company;
use InvalidArgumentException;

class CompanyUserLimitGuard
{
    public function __construct(
        protected CompanyPlanResolver $companyPlanResolver,
    ) {
    }

    public function ensureCanAddUser(Company $company): void
    {
        $maxUsers = $this->companyPlanResolver->limit($company, 'max_users');

        if ($maxUsers === null) {
            return;
        }

        $activeUsers = $company->users()
            ->wherePivot('status', RecordStatus::Active->value)
            ->count();

        if ($activeUsers >= $maxUsers) {
            throw new InvalidArgumentException(sprintf(
                'El plan actual permite hasta %d usuario(s) activos en esta empresa. Debes ampliar el plan antes de agregar otro.',
                $maxUsers,
            ));
        }
    }
}
