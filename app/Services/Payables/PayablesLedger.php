<?php

namespace App\Services\Payables;

use App\Enums\PayableMovementType;
use App\Enums\PurchaseStatus;
use App\Models\PayableMovement;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayablesLedger
{
    public function recordPurchaseCharge(Purchase $purchase, string $amount, ?string $reference = null): PayableMovement
    {
        $amount = $this->normalizeAmount($amount);
        $balanceAfter = bcadd((string) $purchase->balance_due, $amount, 2);

        $movement = PayableMovement::query()->create([
            'company_id' => $purchase->company_id,
            'supplier_id' => $purchase->supplier_id,
            'purchase_id' => $purchase->id,
            'movement_type' => PayableMovementType::PurchaseCharge->value,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'supplier_credit_after' => $this->currentSupplierCreditBalance($purchase),
            'reference' => $reference,
            'occurred_at' => now(),
        ]);

        $purchase->update([
            'balance_due' => $balanceAfter,
            'amount_paid' => (string) $purchase->amount_paid,
            'status' => PurchaseStatus::Confirmed->value,
            'paid_at' => null,
        ]);

        return $movement;
    }

    /**
     * $occurredAt permite registrar un pago inicial (al crear la compra) con
     * la fecha real de la compra (`purchased_at`) en vez de "ahora": un
     * registro tardio de una compra que ya se pago hace dias no debe
     * aparecer como candidata de "Pagos de caja" del cuadre de HOY (ver
     * CashSessionsPage::purchasePaymentCandidates(), que filtra por
     * occurred_at) — igual que Sale::sold_at para las ventas. Un abono
     * posterior sobre una compra ya existente (RegisterPurchasePayment) no
     * pasa este parametro: ese dinero si se mueve "ahora".
     */
    public function recordPayment(Purchase $purchase, string $amount, ?string $reference = null, ?string $paymentMethodCode = null, ?\DateTimeInterface $occurredAt = null): PayableMovement
    {
        $amount = $this->normalizeAmount($amount);
        $outstanding = $this->outstandingForPurchase($purchase);

        if (bccomp($outstanding, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('La compra ya no tiene saldo pendiente.');
        }

        if (bccomp($amount, $outstanding, 2) === 1) {
            throw new InvalidArgumentException('El pago no puede superar el saldo pendiente de la compra.');
        }

        $balanceAfter = bcsub($outstanding, $amount, 2);
        $amountPaid = bcadd((string) $purchase->amount_paid, $amount, 2);
        $status = bccomp($balanceAfter, '0.00', 2) === 0
            ? PurchaseStatus::Paid->value
            : PurchaseStatus::PartiallyPaid->value;

        $movement = PayableMovement::query()->create([
            'company_id' => $purchase->company_id,
            'supplier_id' => $purchase->supplier_id,
            'purchase_id' => $purchase->id,
            'movement_type' => PayableMovementType::Payment->value,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'supplier_credit_after' => $this->currentSupplierCreditBalance($purchase),
            'reference' => $reference,
            'payment_method_code' => $paymentMethodCode,
            'occurred_at' => $occurredAt ?? now(),
        ]);

        $purchase->update([
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceAfter,
            'status' => $status,
            'paid_at' => $status === PurchaseStatus::Paid->value ? now() : null,
        ]);

        return $movement;
    }

    public function recordPurchaseReturnAdjustment(Purchase $purchase, string $amount, ?string $reference = null): PayableMovement
    {
        $amount = $this->normalizeAmount($amount);
        $outstanding = $this->outstandingForPurchase($purchase);

        if (bccomp($amount, $outstanding, 2) === 1) {
            throw new InvalidArgumentException('El ajuste no puede superar el saldo pendiente de la compra.');
        }

        $balanceAfter = bcsub($outstanding, $amount, 2);

        $movement = PayableMovement::query()->create([
            'company_id' => $purchase->company_id,
            'supplier_id' => $purchase->supplier_id,
            'purchase_id' => $purchase->id,
            'movement_type' => PayableMovementType::PurchaseReturnAdjustment->value,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'supplier_credit_after' => $this->currentSupplierCreditBalance($purchase),
            'reference' => $reference,
            'occurred_at' => now(),
        ]);

        $purchase->update([
            'balance_due' => $balanceAfter,
            'status' => PurchaseStatus::Cancelled->value,
            'paid_at' => null,
        ]);

        return $movement;
    }

    public function reconcilePurchaseReturn(Purchase $purchase, string $amount, ?string $reference = null): array
    {
        $amount = $this->normalizeAmount($amount);
        $movements = [];
        $outstanding = $this->outstandingForPurchase($purchase);
        $paidAmount = bcadd((string) $purchase->amount_paid, '0', 2);

        if (bccomp($outstanding, '0.00', 2) === 1) {
            $adjustment = bccomp($amount, $outstanding, 2) === 1 ? $outstanding : $amount;
            $movements[] = $this->recordPurchaseReturnAdjustment($purchase, $adjustment, $reference);
        } else {
            $purchase->update([
                'status' => PurchaseStatus::Cancelled->value,
            ]);
        }

        if (bccomp($paidAmount, '0.00', 2) === 1) {
            $movements[] = $this->recordSupplierCreditGenerated($purchase, $paidAmount, $reference);
            $purchase->update([
                'status' => PurchaseStatus::Cancelled->value,
            ]);
        }

        return $movements;
    }

    public function applySupplierCredit(Purchase $purchase, string $amount, ?string $reference = null): PayableMovement
    {
        if (! $purchase->supplier_id) {
            throw new InvalidArgumentException('La compra no tiene un proveedor formal enlazado.');
        }

        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($purchase, $amount, $reference) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchase->id);
            $supplier = Supplier::query()
                ->lockForUpdate()
                ->findOrFail($purchase->supplier_id);

            $outstanding = $this->outstandingForPurchase($purchase);

            if (bccomp($outstanding, '0.00', 2) !== 1) {
                throw new InvalidArgumentException('La compra ya no tiene saldo pendiente.');
            }

            if (bccomp($amount, $outstanding, 2) === 1) {
                throw new InvalidArgumentException('El credito aplicado no puede superar el saldo pendiente de la compra.');
            }

            $supplierCredit = bcadd((string) $supplier->credit_balance, '0', 2);

            if (bccomp($amount, $supplierCredit, 2) === 1) {
                throw new InvalidArgumentException('El proveedor no tiene saldo a favor suficiente.');
            }

            $balanceAfter = bcsub($outstanding, $amount, 2);
            $amountPaid = bcadd((string) $purchase->amount_paid, $amount, 2);
            $supplierCreditAfter = bcsub($supplierCredit, $amount, 2);
            $status = bccomp($balanceAfter, '0.00', 2) === 0
                ? PurchaseStatus::Paid->value
                : PurchaseStatus::PartiallyPaid->value;

            $movement = PayableMovement::query()->create([
                'company_id' => $purchase->company_id,
                'supplier_id' => $supplier->id,
                'purchase_id' => $purchase->id,
                'movement_type' => PayableMovementType::SupplierCreditApplied->value,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'supplier_credit_after' => $supplierCreditAfter,
                'reference' => $reference,
                'occurred_at' => now(),
            ]);

            $supplier->update([
                'credit_balance' => $supplierCreditAfter,
            ]);

            $purchase->update([
                'amount_paid' => $amountPaid,
                'balance_due' => $balanceAfter,
                'status' => $status,
                'paid_at' => $status === PurchaseStatus::Paid->value ? now() : null,
            ]);

            return $movement;
        });
    }

    public function outstandingForPurchase(Purchase $purchase): string
    {
        return bcadd((string) $purchase->balance_due, '0', 2);
    }

    protected function recordSupplierCreditGenerated(Purchase $purchase, string $amount, ?string $reference = null): PayableMovement
    {
        if (! $purchase->supplier_id) {
            throw new InvalidArgumentException('La compra no tiene un proveedor formal enlazado.');
        }

        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($purchase, $amount, $reference) {
            $supplier = Supplier::query()
                ->lockForUpdate()
                ->findOrFail($purchase->supplier_id);

            $supplierCreditAfter = bcadd((string) $supplier->credit_balance, $amount, 2);

            $movement = PayableMovement::query()->create([
                'company_id' => $purchase->company_id,
                'supplier_id' => $supplier->id,
                'purchase_id' => $purchase->id,
                'movement_type' => PayableMovementType::SupplierCreditGenerated->value,
                'amount' => $amount,
                'balance_after' => (string) $purchase->balance_due,
                'supplier_credit_after' => $supplierCreditAfter,
                'reference' => $reference,
                'occurred_at' => now(),
            ]);

            $supplier->update([
                'credit_balance' => $supplierCreditAfter,
            ]);

            return $movement;
        });
    }

    protected function currentSupplierCreditBalance(Purchase $purchase): string
    {
        if (! $purchase->supplier_id) {
            return '0.00';
        }

        return bcadd((string) optional($purchase->supplier)->credit_balance, '0', 2);
    }

    protected function normalizeAmount(string $amount): string
    {
        $amount = trim($amount);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('Monto invalido.');
        }

        $normalized = bcadd($amount, '0', 2);

        if (bccomp($normalized, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor a cero.');
        }

        return $normalized;
    }
}
