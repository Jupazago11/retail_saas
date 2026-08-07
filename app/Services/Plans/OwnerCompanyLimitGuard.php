<?php

namespace App\Services\Plans;

use App\Models\User;
use InvalidArgumentException;

class OwnerCompanyLimitGuard
{
    public function __construct(
        protected CompanyPlanResolver $companyPlanResolver,
    ) {
    }

    public function ensureCanCreateCompany(User $owner): void
    {
        $ownedCompanies = $owner->ownedCompanies()
            ->whereNull('deleted_at')
            ->get();

        if ($ownedCompanies->isEmpty()) {
            return;
        }

        $maxCompanies = $ownedCompanies
            ->map(fn ($company) => $this->companyPlanResolver->limit($company, 'max_companies'))
            ->filter(fn ($limit) => $limit !== null)
            ->max();

        if ($maxCompanies === null) {
            return;
        }

        if ($ownedCompanies->count() >= $maxCompanies) {
            throw new InvalidArgumentException(sprintf(
                'El limite actual permite hasta %d empresa(s) para este propietario. Debes ampliar el plan antes de crear otra.',
                $maxCompanies,
            ));
        }
    }
}
