<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyMovement extends Model
{
    protected $fillable = [
        'company_id',
        'loyalty_account_id',
        'sale_id',
        'movement_type',
        'points',
        'cash_equivalent',
        'balance_after',
        'notes',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:4',
            'cash_equivalent' => 'decimal:2',
            'balance_after' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
