<?php

namespace App\Actions\Cash;

use App\Enums\CashSessionStatus;
use App\Enums\PaymentStatus;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseCashSession
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, CashSession $cashSession, array $attributes): CashSession
    {
        if ($cashSession->company_id !== $company->id) {
            throw new InvalidArgumentException('La sesion de caja no pertenece a la empresa indicada.');
        }

        $closer = $this->resolveUser($company, (int) ($attributes['closed_by'] ?? 0));
        $denominationBreakdown = $this->resolveDenominationBreakdown($attributes);
        $countedAmount = $denominationBreakdown !== null
            ? array_reduce($denominationBreakdown, fn (string $carry, array $row) => bcadd($carry, bcmul((string) $row['value'], (string) $row['quantity'], 2), 2), '0.00')
            : $this->normalizeAmount($attributes['closing_counted_amount'] ?? null, 'Monto de cierre invalido.');

        $beforeCashSession = $cashSession->fresh();

        $cashSession = DB::transaction(function () use ($company, $cashSession, $closer, $countedAmount, $denominationBreakdown) {
            $cashSession = CashSession::query()
                ->lockForUpdate()
                ->with('payments')
                ->findOrFail($cashSession->id);

            if ($cashSession->status !== CashSessionStatus::Open->value) {
                throw new InvalidArgumentException('La sesion de caja no esta abierta.');
            }

            $cashPayments = $cashSession->payments()
                ->where('status', PaymentStatus::Confirmed->value)
                ->where('payment_method_code', 'cash')
                ->sum('amount');

            $expenses = $cashSession->expenses()->sum('amount');

            $expectedAmount = bcadd((string) $cashSession->opening_amount, number_format((float) $cashPayments, 2, '.', ''), 2);
            $expectedAmount = bcsub($expectedAmount, number_format((float) $expenses, 2, '.', ''), 2);
            $differenceAmount = bcsub($countedAmount, $expectedAmount, 2);
            $status = bccomp($differenceAmount, '0.00', 2) === 0
                ? CashSessionStatus::Reconciled->value
                : CashSessionStatus::Closed->value;

            $cashSession->update([
                'closed_by' => $closer->id,
                'status' => $status,
                'closing_expected_amount' => $expectedAmount,
                'closing_counted_amount' => $countedAmount,
                'closing_denomination_breakdown' => $denominationBreakdown,
                'difference_amount' => $differenceAmount,
                'closed_at' => now(),
            ]);

            return $cashSession->fresh(['branch', 'cashRegister', 'opener', 'closer']);
        });

        $this->auditLogger->logUpdated($company, 'cash_session.closed', $beforeCashSession, $cashSession, $closer);

        return $cashSession;
    }

    protected function resolveDenominationBreakdown(array $attributes): ?array
    {
        if (! array_key_exists('denomination_breakdown', $attributes) || ! is_array($attributes['denomination_breakdown'])) {
            return null;
        }

        $breakdown = [];

        foreach ($attributes['denomination_breakdown'] as $row) {
            $value = (int) ($row['value'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($value <= 0 || $quantity <= 0) {
                continue;
            }

            $breakdown[] = ['value' => $value, 'quantity' => $quantity];
        }

        return $breakdown;
    }

    protected function resolveUser(Company $company, int $userId): User
    {
        return User::query()
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $company->id))
            ->findOrFail($userId);
    }

    protected function normalizeAmount(mixed $value, string $message): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException($message);
        }

        return bcadd($value, '0', 2);
    }
}
