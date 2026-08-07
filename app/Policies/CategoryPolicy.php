<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\InteractsWithCompanyPermissions;

class CategoryPolicy
{
    use InteractsWithCompanyPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'masters.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $this->allows($user, 'masters.view', $category->company_id);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'masters.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $this->allows($user, 'masters.update', $category->company_id);
    }

    public function archive(User $user, Category $category): bool
    {
        return $this->allows($user, 'masters.archive', $category->company_id);
    }

    public function restore(User $user, Category $category): bool
    {
        return $this->allows($user, 'masters.restore', $category->company_id);
    }
}
