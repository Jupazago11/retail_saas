<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\InteractsWithCompanyPermissions;

class ProductPolicy
{
    use InteractsWithCompanyPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->allows($user, 'products.view', $product->company_id);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->allows($user, 'products.update', $product->company_id);
    }

    public function archive(User $user, Product $product): bool
    {
        return $this->allows($user, 'products.archive', $product->company_id);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->allows($user, 'products.restore', $product->company_id);
    }
}
