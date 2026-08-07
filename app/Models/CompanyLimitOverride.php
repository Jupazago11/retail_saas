<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLimitOverride extends Model
{
    protected $fillable = [
        'company_id',
        'limit_key',
        'limit_value',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'limit_value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
