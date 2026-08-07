<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoleTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'scope',
        'status',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_template_permissions')
            ->withTimestamps();
    }
}
