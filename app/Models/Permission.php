<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'code',
        'name',
        'module_code',
        'status',
    ];

    public function companyRoles(): BelongsToMany
    {
        return $this->belongsToMany(CompanyRole::class, 'company_role_permissions')
            ->withTimestamps();
    }
}
