<?php

namespace App\Actions\Credit;

use App\Enums\CashSessionStatus;
use App\Enums\CreditAccountStatus;
use App\Enums\PaymentStatus;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Credit\CreditLedger;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterCreditPayment
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected CreditLedger $creditLedger,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, Sale $sale, array $attributes): Payment
    {
        if ($sale->company_id !== $company->id) {
            throw new InvalidArgumentException('La venta no pertenece a la empresa indicada.');
        }

        $sale = $sale->fresh(['creditAccount', 'payments']);

        if ($sale->sale_type !== 'credit') {
            throw new InvalidArgumentException('Solo se pueden registrar abonos sobre ventas a credito.');
        }

        if (! $sale->creditAccount) {
            throw new InvalidArgumentException('La venta no tiene una cuenta de credito asociada.');
        }

        if (! in_array($sale->creditAccount->status, [
            CreditAccountStatus::Active->value,
            CreditAccountStatus::Blocked->value,
        ], true)) {
            throw new InvalidArgumentException('La cuenta de credito no permite registrar abonos.');
        }

        if ($sale->status === 'cancelled') {
            throw new InvalidArgumentException('No se pueden registrar abonos sobre una venta anulada.');
        }

        $receivedBy = $this->resolveUser($company, (int) ($attributes['received_by'] ?? 0));
        $cashSession = $this->resolveCashSession($company, $sale, $attributes['cash_session_id'] ?? null);
        $methodCode = $this->normalizeMethodCode($attributes['payment_method_code'] ?? null);
        $amount = $this->normalizeAmount($attributes['amount'] ?? null);
        $reference = $this->blankToNull($attributes['reference'] ?? null);

        if ($this->companySettings->get($company, 'pos', 'requires_open_cash_session') && ! $cashSession) {
            throw new InvalidArgumentException('La empresa requiere una sesion de caja abierta para registrar abonos.');
        }

        return DB::transaction(function () use ($company, $sale, $receivedBy, $cashSession, $methodCode, $amount, $reference) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['creditAccount'])
                ->findOrFail($sale->id);

            $outstanding = $this->creditLedger->outstandingForSale($sale);

            if (bccomp($outstanding, '0.00', 2) !== 1) {
                throw new InvalidArgumentException('La venta ya no tiene saldo pendiente.');
            }

            if (bccomp($amount, $outstanding, 2) === 1) {
                throw new InvalidArgumentException('El abono no puede superar el saldo pendiente de la venta.');
            }

            $payment = Payment::query()->create([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'credit_account_id' => $sale->creditAccount->id,
                'cash_session_id' => $cashSession?->id,
                'payment_method_code' => $methodCode,
                'status' => PaymentStatus::Confirmed->value,
                'amount' => $amount,
                'reference' => $reference,
                'paid_at' => now(),
                'received_by' => $receivedBy->id,
            ]);

            $this->creditLedger->recordPayment($sale->creditAccount, $sale, $amount, $reference);

            $payment = $payment->fresh(['sale', 'creditAccount', 'cashSession', 'receiver']);
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

    protected function resolveCashSession(Company $company, Sale $sale, mixed $cashSessionId): ?CashSession
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

        if ((int) $cashSession->branch_id !== (int) $sale->branch_id) {
            throw new InvalidArgumentException('La sesion de caja debe pertenecer a la misma sucursal de la venta.');
        }

        if ($sale->cash_register_id !== null && (int) $cashSession->cash_register_id !== (int) $sale->cash_register_id) {
            throw new InvalidArgumentException('La sesion de caja debe corresponder a la misma caja operativa de la venta.');
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
