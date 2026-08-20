<?php

namespace App\Actions\Companies;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\User;
use App\Services\Plans\CompanyUserLimitGuard;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttachUserToCompany
{
    public function __construct(
        protected CompanyUserLimitGuard $companyUserLimitGuard,
    ) {
    }

    public function handle(Company $company, User $user, array $attributes = []): void
    {
        if ($company->users()->whereKey($user->id)->exists()) {
            throw new InvalidArgumentException('El usuario ya esta vinculado a esta empresa.');
        }

        $companyRoleId = $this->normalizeNullableInt($attributes['company_role_id'] ?? null);

        if ($companyRoleId === null) {
            throw new InvalidArgumentException('Debes elegir un rol para vincular al usuario.');
        }

        $role = CompanyRole::query()
            ->where('company_id', $company->id)
            ->where('status', RecordStatus::Active->value)
            ->find($companyRoleId);

        if (! $role) {
            throw new InvalidArgumentException('El rol seleccionado no existe o esta inactivo para esta empresa.');
        }

        $this->companyUserLimitGuard->ensureCanAddUser($company);

        DB::transaction(function () use ($company, $user, $companyRoleId) {
            $company->users()->attach($user->id, [
                'company_role' => 'custom',
                'company_role_id' => $companyRoleId,
                'status' => RecordStatus::Active->value,
                'joined_at' => now(),
            ]);
        });
    }

    protected function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
