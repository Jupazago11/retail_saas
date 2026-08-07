<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'company_id',
        'person_id',
        'status',
        'credit_balance',
        'payment_term_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'credit_balance' => 'decimal:2',
            'payment_term_days' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payableMovements(): HasMany
    {
        return $this->hasMany(PayableMovement::class);
    }
}
