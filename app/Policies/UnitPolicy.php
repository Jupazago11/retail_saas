<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use App\Policies\Concerns\InteractsWithCompanyPermissions;

class UnitPolicy
{
    use InteractsWithCompanyPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'masters.view');
    }

    public function view(User $user, Unit $unit): bool
    {
        return $this->allows($user, 'masters.view', $unit->company_id);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'masters.create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->allows($user, 'masters.update', $unit->company_id);
    }

    public function archive(User $user, Unit $unit): bool
    {
        return $this->allows($user, 'masters.archive', $unit->company_id);
    }

    public function restore(User $user, Unit $unit): bool
    {
        return $this->allows($user, 'masters.restore', $unit->company_id);
    }
}
