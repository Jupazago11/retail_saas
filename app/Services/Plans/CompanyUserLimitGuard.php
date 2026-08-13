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
        $this->ensureUnderLimit(
            $company,
            'El plan actual permite hasta %d usuario(s) activos en esta empresa. Debes ampliar el plan antes de agregar otro.',
        );
    }

    public function ensureCanActivateUser(Company $company): void
    {
        $this->ensureUnderLimit(
            $company,
            'El plan actual permite hasta %d usuario(s) activos en esta empresa. Desactiva a otro usuario antes de activar este.',
        );
    }

    protected function ensureUnderLimit(Company $company, string $message): void
    {
        $maxUsers = $this->companyPlanResolver->limit($company, 'max_users');

        if ($maxUsers === null) {
            return;
        }

        $activeUsers = $company->users()
            ->wherePivot('status', RecordStatus::Active->value)
            ->count();

        if ($activeUsers >= $maxUsers) {
            throw new InvalidArgumentException(sprintf($message, $maxUsers));
        }
    }
}
