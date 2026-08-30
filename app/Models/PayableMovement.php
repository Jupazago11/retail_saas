<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayableMovement extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_id',
        'movement_type',
        'amount',
        'balance_after',
        'supplier_credit_after',
        'reference',
        'payment_method_code',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'supplier_credit_after' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cashSessionExpense(): HasOne
    {
        return $this->hasOne(CashSessionExpense::class);
    }
}
