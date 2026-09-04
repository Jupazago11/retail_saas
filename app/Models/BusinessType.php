<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'icon',
        'status',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }
}
