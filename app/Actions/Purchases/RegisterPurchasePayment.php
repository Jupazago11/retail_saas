<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseStatus;
use App\Models\Company;
use App\Models\Purchase;
use App\Services\Audit\AuditLogger;
use App\Services\Payables\PayablesLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterPurchasePayment
{
    public function __construct(
        protected PayablesLedger $payablesLedger,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, Purchase $purchase, array $attributes): Purchase
    {
        if ($purchase->company_id !== $company->id) {
            throw new InvalidArgumentException('La compra no pertenece a la empresa indicada.');
        }

        $purchase = $purchase->fresh();

        if (! in_array($purchase->status, [
            PurchaseStatus::Confirmed->value,
            PurchaseStatus::PartiallyPaid->value,
        ], true)) {
            throw new InvalidArgumentException('Solo se pueden registrar pagos sobre compras pendientes.');
        }

        $amount = $this->normalizeAmount($attributes['amount'] ?? null);
        $reference = $this->blankToNull($attributes['reference'] ?? null);
        $beforePurchase = $purchase->fresh();

        return DB::transaction(function () use ($company, $purchase, $amount, $reference, $beforePurchase) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            $this->payablesLedger->recordPayment($purchase, $amount, $reference);
            $purchase = $purchase->fresh(['payableMovements']);
            $this->auditLogger->logUpdated($company, 'purchase.payment_registered', $beforePurchase, $purchase);

            return $purchase;
        });
    }

    protected function normalizeAmount(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Monto de pago invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El pago debe ser mayor a cero.');
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
