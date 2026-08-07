<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'code',
        'name',
        'status',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_modules')
            ->withPivot('enabled')
            ->withTimestamps();
    }

    public function companyOverrides(): HasMany
    {
        return $this->hasMany(CompanyModuleOverride::class);
    }
}
