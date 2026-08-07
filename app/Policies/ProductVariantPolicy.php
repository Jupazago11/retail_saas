<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;
use App\Policies\Concerns\InteractsWithCompanyPermissions;

class ProductVariantPolicy
{
    use InteractsWithCompanyPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'products.view');
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return $this->allows($user, 'products.view', $variant->company_id);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'products.create');
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $this->allows($user, 'products.update', $variant->company_id);
    }

    public function archive(User $user, ProductVariant $variant): bool
    {
        return $this->allows($user, 'products.archive', $variant->company_id);
    }

    public function restore(User $user, ProductVariant $variant): bool
    {
        return $this->allows($user, 'products.restore', $variant->company_id);
    }
}
