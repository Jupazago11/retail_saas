<?php

namespace App\Actions\Sales;

use App\Enums\CashSessionStatus;
use App\Enums\PaymentStatus;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterSalePayments
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, Sale $sale, array $attributes): array
    {
        if ($sale->company_id !== $company->id) {
            throw new InvalidArgumentException('La venta no pertenece a la empresa indicada.');
        }

        $payments = $attributes['payments'] ?? [];

        if (! is_array($payments) || $payments === []) {
            throw new InvalidArgumentException('Debe registrar al menos un pago.');
        }

        $sale = $sale->fresh(['payments']);

        if ($sale->status !== 'confirmed') {
            throw new InvalidArgumentException('Solo se pueden registrar pagos sobre ventas confirmadas.');
        }

        if ($sale->sale_type === 'credit') {
            throw new InvalidArgumentException('Las ventas a credito registran abonos, no pagos inmediatos de venta.');
        }

        if ($sale->payments()->where('status', PaymentStatus::Confirmed->value)->exists()) {
            throw new InvalidArgumentException('La venta ya tiene pagos confirmados registrados.');
        }

        $receivedBy = $this->resolveUser($company, (int) ($attributes['received_by'] ?? 0));
        $cashSession = $this->resolveCashSession($company, $attributes['cash_session_id'] ?? null);

        if ($this->companySettings->get($company, 'pos', 'requires_open_cash_session') && ! $cashSession) {
            throw new InvalidArgumentException('La empresa requiere una sesion de caja abierta para registrar pagos.');
        }

        $normalizedPayments = collect($payments)
            ->map(function (array $payment) {
                $methodCode = $this->normalizeMethodCode($payment['payment_method_code'] ?? null);
                $amount = $this->normalizeAmount($payment['amount'] ?? null);

                return [
                    'payment_method_code' => $methodCode,
                    'amount' => $amount,
                    'reference' => $this->blankToNull($payment['reference'] ?? null),
                ];
            })
            ->all();

        $grandTotal = (string) $sale->grand_total;
        $totalPayments = array_reduce($normalizedPayments, fn (string $carry, array $payment) => bcadd($carry, $payment['amount'], 2), '0.00');

        if (bccomp($totalPayments, $grandTotal, 2) < 0) {
            throw new InvalidArgumentException('El monto pagado no cubre el total de la venta.');
        }

        if (bccomp($totalPayments, $grandTotal, 2) > 0) {
            $excess = bcsub($totalPayments, $grandTotal, 2);
            $cashTotal = array_reduce(
                array_filter($normalizedPayments, fn (array $p) => $p['payment_method_code'] === 'cash'),
                fn (string $carry, array $p) => bcadd($carry, $p['amount'], 2),
                '0.00'
            );
            if (bccomp($excess, $cashTotal, 2) > 0) {
                throw new InvalidArgumentException('Solo el efectivo puede exceder el total. Ajuste los montos de los otros metodos de pago.');
            }
            foreach (array_reverse(array_keys($normalizedPayments)) as $i) {
                if ($normalizedPayments[$i]['payment_method_code'] !== 'cash') {
                    continue;
                }
                $cashAmt = $normalizedPayments[$i]['amount'];
                if (bccomp($cashAmt, $excess, 2) >= 0) {
                    $normalizedPayments[$i]['amount'] = bcsub($cashAmt, $excess, 2);
                    $excess = '0.00';
                    break;
                }
                $excess = bcsub($excess, $cashAmt, 2);
                $normalizedPayments[$i]['amount'] = '0.00';
            }
            $normalizedPayments = array_values(
                array_filter($normalizedPayments, fn (array $p) => bccomp($p['amount'], '0.00', 2) > 0)
            );
        }

        return DB::transaction(function () use ($company, $sale, $receivedBy, $cashSession, $normalizedPayments) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($sale->payments()->where('status', PaymentStatus::Confirmed->value)->exists()) {
                throw new InvalidArgumentException('La venta ya tiene pagos confirmados registrados.');
            }

            return collect($normalizedPayments)
                ->map(function (array $payment) use ($company, $sale, $receivedBy, $cashSession) {
                    $payment = Payment::query()->create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'cash_session_id' => $cashSession?->id,
                        'payment_method_code' => $payment['payment_method_code'],
                        'status' => PaymentStatus::Confirmed->value,
                        'amount' => $payment['amount'],
                        'reference' => $payment['reference'],
                        'paid_at' => now(),
                        'received_by' => $receivedBy->id,
                    ]);

                    $payment = $payment->fresh(['sale', 'cashSession', 'receiver']);
                    $this->auditLogger->logCreated($company, 'sale.payment_registered', $payment, $receivedBy);

                    return $payment;
                })
                ->all();
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
            throw new InvalidArgumentException('La sesion de caja debe estar abierta para registrar pagos.');
        }

        return $cashSession;
    }

    protected function normalizeMethodCode(mixed $value): string
    {
        $methodCode = $this->blankToNull($value);

        if ($methodCode === null) {
            throw new InvalidArgumentException('Cada pago requiere un metodo.');
        }

        return $methodCode;
    }

    protected function normalizeAmount(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Monto de pago invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('Cada pago debe ser mayor a cero.');
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
