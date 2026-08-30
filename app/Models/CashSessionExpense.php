<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashSessionExpense extends Model
{
    protected $fillable = [
        'company_id',
        'company_sequence',
        'cash_session_id',
        'payable_movement_id',
        'description',
        'amount',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'company_sequence' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function payableMovement(): BelongsTo
    {
        return $this->belongsTo(PayableMovement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
