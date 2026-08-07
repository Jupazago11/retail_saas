<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = [
        'company_id',
        'company_sequence',
        'branch_id',
        'cash_register_id',
        'opened_by',
        'closed_by',
        'status',
        'opening_amount',
        'closing_expected_amount',
        'closing_counted_amount',
        'difference_amount',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'company_sequence' => 'integer',
            'opening_amount' => 'decimal:2',
            'closing_expected_amount' => 'decimal:2',
            'closing_counted_amount' => 'decimal:2',
            'difference_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
