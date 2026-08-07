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

    public function roleTemplates(): BelongsToMany
    {
        return $this->belongsToMany(RoleTemplate::class, 'role_template_permissions')
            ->withTimestamps();
    }

    public function companyRoles(): BelongsToMany
    {
        return $this->belongsToMany(CompanyRole::class, 'company_role_permissions')
            ->withTimestamps();
    }
}
