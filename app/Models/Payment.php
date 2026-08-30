<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'company_id',
        'sale_id',
        'credit_account_id',
        'cash_session_id',
        'payment_method_code',
        'status',
        'amount',
        'reference',
        'paid_at',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Un pago cuya venta relacionada tiene `sold_at` de un dia calendario
     * distinto al de apertura de `$cashSession` es una venta tardia
     * (backdateada): el dinero no entro fisicamente ese dia a esa caja, asi
     * que no debe contar para el efectivo esperado ni la venta diaria del
     * cuadre (ver CashSessionsPage, CloseCashSession, RecomputesClosedSessionTotals).
     * Un abono a credito (`sale_id` null) no tiene esa fecha, siempre cuenta.
     */
    public function belongsToCashSessionDay(CashSession $cashSession): bool
    {
        $soldAt = $this->sale?->sold_at;

        return $soldAt === null || $soldAt->isSameDay($cashSession->opened_at);
    }

    public function scopeConfirmedForCashSessionDay(Builder $query, CashSession $cashSession): Builder
    {
        return $query
            ->where('status', PaymentStatus::Confirmed->value)
            ->where(function (Builder $q) use ($cashSession) {
                $q->whereNull('sale_id')
                    ->orWhereHas('sale', function (Builder $saleQuery) use ($cashSession) {
                        $saleQuery->whereNull('sold_at')
                            ->orWhereDate('sold_at', $cashSession->opened_at->toDateString());
                    });
            });
    }
}
