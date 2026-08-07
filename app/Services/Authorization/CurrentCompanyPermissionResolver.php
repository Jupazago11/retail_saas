<?php

namespace App\Services\Authorization;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;

class CurrentCompanyPermissionResolver
{
    public function __construct(
        protected CurrentCompany $currentCompany,
    ) {
    }

    public function has(User $user, string $permissionCode, Company|int|null $company = null): bool
    {
        $company = $this->resolveCompany($company);

        if (! $company) {
            return false;
        }

        if ($company->owner_user_id === $user->id) {
            return true;
        }

        $membership = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', RecordStatus::Active->value)
            ->first();

        if (! $membership) {
            return false;
        }

        if (($membership->company_role ?? null) === 'owner') {
            return true;
        }

        if ($this->templateHasPermission((int) ($membership->role_template_id ?? 0), $permissionCode)) {
            return true;
        }

        return $this->companyRoleHasPermission((int) ($membership->company_role_id ?? 0), $permissionCode);
    }

    public function hasAny(User $user, array $permissionCodes, Company|int|null $company = null): bool
    {
        foreach ($permissionCodes as $permissionCode) {
            if ($this->has($user, $permissionCode, $company)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveCompany(Company|int|null $company): ?Company
    {
        if ($company instanceof Company) {
            return $company;
        }

        if (is_int($company)) {
            return Company::query()->find($company);
        }

        return $this->currentCompany->company();
    }

    protected function templateHasPermission(int $roleTemplateId, string $permissionCode): bool
    {
        if ($roleTemplateId <= 0) {
            return false;
        }

        return DB::table('role_template_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_template_permissions.permission_id')
            ->join('role_templates', 'role_templates.id', '=', 'role_template_permissions.role_template_id')
            ->where('role_template_permissions.role_template_id', $roleTemplateId)
            ->where('permissions.code', $permissionCode)
            ->where('permissions.status', RecordStatus::Active->value)
            ->where('role_templates.status', RecordStatus::Active->value)
            ->exists();
    }

    protected function companyRoleHasPermission(int $companyRoleId, string $permissionCode): bool
    {
        if ($companyRoleId <= 0) {
            return false;
        }

        return DB::table('company_role_permissions')
            ->join('permissions', 'permissions.id', '=', 'company_role_permissions.permission_id')
            ->join('company_roles', 'company_roles.id', '=', 'company_role_permissions.company_role_id')
            ->where('company_role_permissions.company_role_id', $companyRoleId)
            ->where('permissions.code', $permissionCode)
            ->where('permissions.status', RecordStatus::Active->value)
            ->where('company_roles.status', RecordStatus::Active->value)
            ->exists();
    }
}
