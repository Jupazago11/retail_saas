<?php

namespace App\Actions\Companies;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\RoleTemplate;
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

        $roleTemplateId = $this->normalizeNullableInt($attributes['role_template_id'] ?? null);
        $companyRoleId = $this->normalizeNullableInt($attributes['company_role_id'] ?? null);

        if (($roleTemplateId === null) === ($companyRoleId === null)) {
            throw new InvalidArgumentException('Debes elegir una plantilla o un rol personalizado para vincular al usuario.');
        }

        if ($roleTemplateId !== null) {
            $template = RoleTemplate::query()
                ->where('status', RecordStatus::Active->value)
                ->find($roleTemplateId);

            if (! $template) {
                throw new InvalidArgumentException('La plantilla seleccionada no existe o esta inactiva.');
            }
        }

        if ($companyRoleId !== null) {
            $role = CompanyRole::query()
                ->where('company_id', $company->id)
                ->where('status', RecordStatus::Active->value)
                ->find($companyRoleId);

            if (! $role) {
                throw new InvalidArgumentException('El rol personalizado seleccionado no existe o esta inactivo para esta empresa.');
            }
        }

        $this->companyUserLimitGuard->ensureCanAddUser($company);

        DB::transaction(function () use ($company, $user, $roleTemplateId, $companyRoleId) {
            $company->users()->attach($user->id, [
                'company_role' => $companyRoleId !== null ? 'custom' : 'template',
                'role_template_id' => $roleTemplateId,
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
