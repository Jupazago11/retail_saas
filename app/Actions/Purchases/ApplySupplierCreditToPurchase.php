<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseStatus;
use App\Models\Company;
use App\Models\Purchase;
use App\Services\Audit\AuditLogger;
use App\Services\Payables\PayablesLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplySupplierCreditToPurchase
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

        $purchase = $purchase->fresh(['supplier']);

        if (! in_array($purchase->status, [
            PurchaseStatus::Confirmed->value,
            PurchaseStatus::PartiallyPaid->value,
        ], true)) {
            throw new InvalidArgumentException('Solo se puede aplicar saldo a favor sobre compras pendientes.');
        }

        $amount = $this->normalizeAmount($attributes['amount'] ?? null);
        $reference = $this->normalizeReference($attributes['reference'] ?? null);
        $beforePurchase = $purchase->fresh();

        return DB::transaction(function () use ($company, $purchase, $amount, $reference, $beforePurchase) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            $this->payablesLedger->applySupplierCredit($purchase, $amount, $reference);
            $purchase = $purchase->fresh(['payableMovements', 'supplier']);
            $this->auditLogger->logUpdated($company, 'purchase.supplier_credit_applied', $beforePurchase, $purchase);

            return $purchase;
        });
    }

    protected function normalizeAmount(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Monto de credito invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El credito aplicado debe ser mayor a cero.');
        }

        return $normalized;
    }

    protected function normalizeReference(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException('Debes indicar una referencia para aplicar saldo a favor.');
        }

        return $value;
    }
}
