<?php

namespace App\Actions\Purchases;

use App\Models\Company;
use App\Models\Purchase;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\ReturnPurchaseFromInventory;
use App\Services\Payables\PayablesLedger;
use InvalidArgumentException;

class ReturnPurchase
{
    public function __construct(
        protected ReturnPurchaseFromInventory $returnPurchaseFromInventory,
        protected PayablesLedger $payablesLedger,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, Purchase $purchase): Purchase
    {
        if ($purchase->company_id !== $company->id) {
            throw new InvalidArgumentException('La compra no pertenece a la empresa indicada.');
        }

        $purchase = $purchase->fresh(['payableMovements']);

        if ($purchase->returned_from_inventory_at !== null) {
            return $purchase;
        }

        if (bccomp((string) $purchase->amount_paid, '0.00', 2) === 1 && ! $purchase->supplier_id) {
            throw new InvalidArgumentException('No se puede devolver una compra pagada sin proveedor formal enlazado.');
        }

        $beforePurchase = $purchase->fresh();
        $purchase = $this->returnPurchaseFromInventory->handle($purchase);
        $this->payablesLedger->reconcilePurchaseReturn($purchase, (string) $purchase->total, $purchase->invoice_number);
        $purchase = $purchase->fresh(['payableMovements']);
        $this->auditLogger->logUpdated($company, 'purchase.returned_from_inventory', $beforePurchase, $purchase);

        return $purchase;
    }
}
