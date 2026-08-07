<?php

namespace App\Services\Tenancy;

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

class CurrentCompany
{
    public const SESSION_KEY = 'current_company_id';

    public function __construct(
        protected Session $session,
    ) {
    }

    public function id(): ?int
    {
        return $this->session->get(self::SESSION_KEY);
    }

    public function company(): ?Company
    {
        $companyId = $this->id();

        if (! $companyId) {
            return null;
        }

        return Company::query()->find($companyId);
    }

    public function setForUser(User $user, Company $company): void
    {
        if (! $user->companies()->whereKey($company->id)->exists()) {
            abort(403, 'La empresa seleccionada no pertenece al usuario autenticado.');
        }

        $this->session->put(self::SESSION_KEY, $company->id);
    }

    public function clearIfInvalidForUser(User $user): void
    {
        $companyId = $this->id();

        if (! $companyId) {
            return;
        }

        if (! $user->companies()->whereKey($companyId)->exists()) {
            $this->clear();
        }
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
