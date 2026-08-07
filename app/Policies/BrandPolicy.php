<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;
use App\Policies\Concerns\InteractsWithCompanyPermissions;

class BrandPolicy
{
    use InteractsWithCompanyPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'masters.view');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'masters.view', $brand->company_id);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'masters.create');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'masters.update', $brand->company_id);
    }

    public function archive(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'masters.archive', $brand->company_id);
    }

    public function restore(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'masters.restore', $brand->company_id);
    }
}
