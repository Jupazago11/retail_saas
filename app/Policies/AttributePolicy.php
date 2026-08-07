<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;
use App\Policies\Concerns\InteractsWithCompanyPermissions;

class AttributePolicy
{
    use InteractsWithCompanyPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'products.view');
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $this->allows($user, 'products.view', $attribute->company_id);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'products.create');
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $this->allows($user, 'products.update', $attribute->company_id);
    }

    public function archive(User $user, Attribute $attribute): bool
    {
        return $this->allows($user, 'products.archive', $attribute->company_id);
    }

    public function restore(User $user, Attribute $attribute): bool
    {
        return $this->allows($user, 'products.restore', $attribute->company_id);
    }
}
