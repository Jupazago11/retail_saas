<?php

namespace App\Actions\Credit;

use App\Enums\CashSessionStatus;
use App\Enums\CreditAccountStatus;
use App\Enums\PaymentStatus;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\CreditAccount;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Credit\CreditLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterCreditPayment
{
    public function __construct(
        protected CreditLedger $creditLedger,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, CreditAccount $account, array $attributes): Payment
    {
        if ($account->company_id !== $company->id) {
            throw new InvalidArgumentException('La cuenta de credito no pertenece a la empresa indicada.');
        }

        if (! in_array($account->status, [
            CreditAccountStatus::Active->value,
            CreditAccountStatus::Blocked->value,
        ], true)) {
            throw new InvalidArgumentException('La cuenta de credito no permite registrar abonos.');
        }

        $receivedBy = $this->resolveUser($company, (int) ($attributes['received_by'] ?? 0));
        $cashSession = $this->resolveCashSession($company, $attributes['cash_session_id'] ?? null);
        $methodCode = $this->normalizeMethodCode($attributes['payment_method_code'] ?? null);
        $amount = $this->normalizeAmount($attributes['amount'] ?? null);
        $reference = $this->blankToNull($attributes['reference'] ?? null);

        return DB::transaction(function () use ($company, $account, $receivedBy, $cashSession, $methodCode, $amount, $reference) {
            $account = CreditAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            if (bccomp((string) $account->balance_due, '0.00', 2) !== 1) {
                throw new InvalidArgumentException('La cuenta ya no tiene saldo pendiente.');
            }

            if (bccomp($amount, (string) $account->balance_due, 2) === 1) {
                throw new InvalidArgumentException('El abono no puede superar el saldo pendiente de la cuenta.');
            }

            $payment = Payment::query()->create([
                'company_id' => $company->id,
                'sale_id' => null,
                'credit_account_id' => $account->id,
                'cash_session_id' => $cashSession?->id,
                'payment_method_code' => $methodCode,
                'status' => PaymentStatus::Confirmed->value,
                'amount' => $amount,
                'reference' => $reference,
                'paid_at' => now(),
                'received_by' => $receivedBy->id,
            ]);

            $this->creditLedger->recordPayment($account, $amount, $reference);

            $payment = $payment->fresh(['creditAccount', 'cashSession', 'receiver']);
            $this->auditLogger->logCreated($company, 'credit.payment_registered', $payment, $receivedBy);

            return $payment;
        });
    }

    protected function resolveUser(Company $company, int $userId): User
    {
        return User::query()
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $company->id))
            ->findOrFail($userId);
    }

    protected function resolveCashSession(Company $company, mixed $cashSessionId): ?CashSession
    {
        if (! $cashSessionId) {
            return null;
        }

        $cashSession = CashSession::query()
            ->where('company_id', $company->id)
            ->findOrFail((int) $cashSessionId);

        if ($cashSession->status !== CashSessionStatus::Open->value) {
            throw new InvalidArgumentException('La sesion de caja debe estar abierta para registrar abonos.');
        }

        return $cashSession;
    }

    protected function normalizeMethodCode(mixed $value): string
    {
        $methodCode = $this->blankToNull($value);

        if ($methodCode === null) {
            throw new InvalidArgumentException('El abono requiere un metodo.');
        }

        return $methodCode;
    }

    protected function normalizeAmount(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Monto de abono invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El abono debe ser mayor a cero.');
        }

        return $normalized;
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
