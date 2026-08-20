<?php

namespace App\Actions\Companies;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\User;
use App\Services\Plans\CompanyUserLimitGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class CreateInternalUserForCompany
{
    public function __construct(
        protected CompanyUserLimitGuard $companyUserLimitGuard,
    ) {
    }

    public function handle(Company $company, array $attributes): User
    {
        $companyRoleId = $this->normalizeNullableInt($attributes['company_role_id'] ?? null);

        if ($companyRoleId === null) {
            throw new InvalidArgumentException('Debes elegir un rol para el nuevo usuario.');
        }

        $role = CompanyRole::query()
            ->where('company_id', $company->id)
            ->where('status', RecordStatus::Active->value)
            ->find($companyRoleId);

        if (! $role) {
            throw new InvalidArgumentException('El rol seleccionado no existe o esta inactivo para esta empresa.');
        }

        $this->companyUserLimitGuard->ensureCanAddUser($company);

        return DB::transaction(function () use ($company, $attributes, $companyRoleId) {
            $user = User::query()->create([
                'name' => trim($attributes['name']),
                'username' => trim($attributes['username']),
                'email' => null,
                'password' => Hash::make($attributes['password']),
                'status' => RecordStatus::Active->value,
                'must_change_password' => true,
            ]);

            $company->users()->attach($user->id, [
                'company_role' => 'custom',
                'company_role_id' => $companyRoleId,
                'status' => RecordStatus::Active->value,
                'joined_at' => now(),
            ]);

            return $user;
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
