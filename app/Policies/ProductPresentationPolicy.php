<?php

namespace App\Policies;

use App\Models\ProductPresentation;
use App\Models\User;
use App\Policies\Concerns\InteractsWithCompanyPermissions;

class ProductPresentationPolicy
{
    use InteractsWithCompanyPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'products.view');
    }

    public function view(User $user, ProductPresentation $presentation): bool
    {
        return $this->allows($user, 'products.view', $presentation->company_id);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'products.create');
    }

    public function update(User $user, ProductPresentation $presentation): bool
    {
        return $this->allows($user, 'products.update', $presentation->company_id);
    }

    public function archive(User $user, ProductPresentation $presentation): bool
    {
        return $this->allows($user, 'products.archive', $presentation->company_id);
    }

    public function restore(User $user, ProductPresentation $presentation): bool
    {
        return $this->allows($user, 'products.restore', $presentation->company_id);
    }
}
