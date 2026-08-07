<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\Authorization\CurrentCompanyPermissionResolver;

trait InteractsWithCompanyPermissions
{
    protected function allows(User $user, string $permissionCode, ?int $companyId = null): bool
    {
        return app(CurrentCompanyPermissionResolver::class)->has($user, $permissionCode, $companyId);
    }
}
