<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\PostPurchaseToInventory;
use App\Services\Payables\PayablesLedger;
use App\Services\Purchases\PurchaseCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CreatePurchase
{
    public function __construct(
        protected PurchaseCalculator $purchaseCalculator,
        protected PostPurchaseToInventory $postPurchaseToInventory,
        protected PayablesLedger $payablesLedger,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $attributes): Purchase
    {
        $branch = $this->resolveBranch($company, (int) ($attributes['branch_id'] ?? 0));
        $warehouse = $this->resolveWarehouse($company, $branch, (int) ($attributes['warehouse_id'] ?? 0));
        $supplier = $this->resolveSupplier($company, $attributes['supplier_id'] ?? null);
        $items = $attributes['items'] ?? [];
        $hasItems = is_array($items) && $items !== [];

        if ($hasItems) {
            $calculatedLines = collect($items)
                ->map(function (array $item) use ($company) {
                    $product = $this->resolveProduct($company, (int) ($item['product_id'] ?? 0));
                    $presentation = $this->resolvePresentation($company, $product, $item['product_presentation_id'] ?? null);
                    $variant = $this->resolveVariant($company, $product, $item['product_variant_id'] ?? null);

                    return $this->purchaseCalculator->calculateLine(
                        $product,
                        $presentation,
                        $variant,
                        $item['quantity'] ?? 0,
                        $item['unit_cost'] ?? 0,
                        $item['tax_rate'] ?? 0,
                    );
                })
                ->all();
            $totals = $this->purchaseCalculator->calculateTotals($calculatedLines);
        } else {
            $calculatedLines = [];
            $manualTotal = bcadd((string) ($attributes['total'] ?? '0'), '0', 2);
            $totals = ['subtotal' => $manualTotal, 'tax_total' => '0.00', 'total' => $manualTotal];
        }

        return DB::transaction(function () use ($company, $branch, $warehouse, $supplier, $attributes, $calculatedLines, $totals, $hasItems) {
            $requestedStatus = $attributes['status'] ?? PurchaseStatus::Confirmed->value;

            $lastSequence = (int) Purchase::query()
                ->where('company_id', $company->id)
                ->orderByDesc('company_sequence')
                ->lockForUpdate()
                ->value('company_sequence');

            $purchase = Purchase::query()->create([
                'company_id' => $company->id,
                'company_sequence' => $lastSequence + 1,
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplier !== null
                    ? $this->resolveSupplierName($supplier)
                    : $this->blankToNull($attributes['supplier_name'] ?? null),
                'invoice_number' => $this->blankToNull($attributes['invoice_number'] ?? null),
                'purchase_type' => $this->blankToDefault($attributes['purchase_type'] ?? null, 'invoice'),
                'status' => $requestedStatus,
                'notes' => $this->blankToNull($attributes['notes'] ?? null),
                'purchased_at' => $attributes['purchased_at'] ?? null,
                'due_at' => $this->resolveDueAt(
                    $attributes['due_at'] ?? null,
                    $attributes['purchased_at'] ?? null,
                    $supplier,
                    (bool) ($attributes['skip_default_due_at'] ?? false),
                ),
                'amount_paid' => '0.00',
                'balance_due' => '0.00',
                'paid_at' => null,
                ...$totals,
            ]);

            if ($hasItems) {
                $purchase->items()->createMany($calculatedLines);
            }

            if ($hasItems && $this->shouldPostToInventory($purchase->status)) {
                $purchase = $this->postPurchaseToInventory->handle($purchase);
            }

            $this->payablesLedger->recordPurchaseCharge($purchase, (string) $purchase->total, $purchase->invoice_number);

            if ($requestedStatus === PurchaseStatus::PartiallyPaid->value || $requestedStatus === PurchaseStatus::Paid->value) {
                foreach ($this->resolveInitialPayments($attributes, $purchase, $requestedStatus) as $payment) {
                    $this->payablesLedger->recordPayment(
                        $purchase,
                        $payment['amount'],
                        'initial_purchase_payment',
                        $payment['payment_method_code']
                    );
                }
            }

            $purchase = $purchase->fresh(['items.product', 'items.presentation', 'items.variant', 'branch', 'warehouse', 'payableMovements']);
            $this->auditLogger->logCreated($company, 'purchase.created', $purchase);

            return $purchase;
        });
    }

    protected function shouldPostToInventory(string $status): bool
    {
        return in_array($status, [
            PurchaseStatus::Confirmed->value,
            PurchaseStatus::PartiallyPaid->value,
            PurchaseStatus::Paid->value,
        ], true);
    }

    /**
     * Los pagos iniciales de una compra recien creada. Acepta 'initial_payments'
     * (lista de {amount, payment_method_code} — usado por el formulario de
     * Compras cuando el pago fue mixto efectivo+transferencia) o, para
     * compatibilidad con imports/seed que solo mandan un monto suelto,
     * 'paid_amount' sin medio de pago especifico.
     *
     * @return list<array{amount: string, payment_method_code: ?string}>
     */
    protected function resolveInitialPayments(array $attributes, Purchase $purchase, string $requestedStatus): array
    {
        if (isset($attributes['initial_payments']) && is_array($attributes['initial_payments']) && $attributes['initial_payments'] !== []) {
            $totalPaid = '0.00';
            $payments = [];

            foreach ($attributes['initial_payments'] as $entry) {
                $amount = $this->normalizePaymentAmount($entry['amount'] ?? null);
                $totalPaid = bcadd($totalPaid, $amount, 2);
                $payments[] = [
                    'amount' => $amount,
                    'payment_method_code' => $this->blankToNull($entry['payment_method_code'] ?? null),
                ];
            }

            $this->assertInitialPaidAmountMatchesStatus($totalPaid, $purchase, $requestedStatus);

            return $payments;
        }

        $paidAmount = $this->normalizePaymentAmount($attributes['paid_amount'] ?? null);
        $this->assertInitialPaidAmountMatchesStatus($paidAmount, $purchase, $requestedStatus);

        return [['amount' => $paidAmount, 'payment_method_code' => null]];
    }

    protected function assertInitialPaidAmountMatchesStatus(string $paidAmount, Purchase $purchase, string $requestedStatus): void
    {
        if ($requestedStatus === PurchaseStatus::PartiallyPaid->value) {
            if (bccomp($paidAmount, '0.00', 2) !== 1 || bccomp($paidAmount, (string) $purchase->total, 2) !== -1) {
                throw new InvalidArgumentException('La compra parcialmente pagada requiere un abono inicial mayor a cero y menor al total.');
            }
        }

        if ($requestedStatus === PurchaseStatus::Paid->value && bccomp($paidAmount, (string) $purchase->total, 2) !== 0) {
            throw new InvalidArgumentException('La compra pagada requiere un pago inicial igual al total.');
        }
    }

    protected function normalizePaymentAmount(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('La compra con pago inicial requiere un monto valido.');
        }

        return bcadd($value, '0', 2);
    }

    protected function resolveBranch(Company $company, int $branchId): Branch
    {
        return Branch::query()
            ->where('company_id', $company->id)
            ->findOrFail($branchId);
    }

    protected function resolveWarehouse(Company $company, Branch $branch, int $warehouseId): Warehouse
    {
        return Warehouse::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->findOrFail($warehouseId);
    }

    protected function resolveProduct(Company $company, int $productId): Product
    {
        return Product::query()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->findOrFail($productId);
    }

    protected function resolvePresentation(Company $company, Product $product, mixed $presentationId): ?ProductPresentation
    {
        if (! $presentationId) {
            return null;
        }

        return ProductPresentation::query()
            ->where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->whereNull('deleted_at')
            ->findOrFail((int) $presentationId);
    }

    protected function resolveVariant(Company $company, Product $product, mixed $variantId): ?ProductVariant
    {
        if (! $variantId) {
            return null;
        }

        return ProductVariant::query()
            ->where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->findOrFail((int) $variantId);
    }

    protected function resolveSupplier(Company $company, mixed $supplierId): ?Supplier
    {
        if (! $supplierId) {
            return null;
        }

        return Supplier::query()
            ->where('company_id', $company->id)
            ->with('person')
            ->findOrFail((int) $supplierId);
    }

    protected function resolveSupplierName(Supplier $supplier): string
    {
        $parts = array_filter([
            trim((string) $supplier->person?->first_name),
            trim((string) $supplier->person?->last_name),
        ]);

        return implode(' ', $parts);
    }

    protected function resolveDueAt(mixed $dueAt, mixed $purchasedAt, ?Supplier $supplier, bool $skipDefault = false): mixed
    {
        if ($this->hasValue($dueAt)) {
            return $dueAt;
        }

        if ($skipDefault || $supplier?->payment_term_days === null) {
            return null;
        }

        $baseDate = $this->hasValue($purchasedAt) ? $purchasedAt : now();

        return Carbon::parse($baseDate)->addDays($supplier->payment_term_days);
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function blankToDefault(mixed $value, string $default): string
    {
        $value = $this->blankToNull($value);

        return $value ?? $default;
    }

    protected function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }
}
