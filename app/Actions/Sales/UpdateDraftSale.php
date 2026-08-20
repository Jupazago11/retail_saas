<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use App\Services\Credit\CreditLedger;
use App\Services\Inventory\PostSaleToInventory;
use App\Services\Loyalty\LoyaltyLedger;
use App\Services\Plans\CompanyOperationalLimitGuard;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Promotions\PromotionEngine;
use App\Services\Sales\SaleCalculator;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateDraftSale
{
    public function __construct(
        protected SaleCalculator $saleCalculator,
        protected PostSaleToInventory $postSaleToInventory,
        protected CompanySettings $companySettings,
        protected CreditLedger $creditLedger,
        protected LoyaltyLedger $loyaltyLedger,
        protected CompanyOperationalLimitGuard $companyOperationalLimitGuard,
        protected CompanyPlanResolver $companyPlanResolver,
        protected PromotionEngine $promotionEngine,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, Sale $sale, array $attributes): Sale
    {
        if ($sale->company_id !== $company->id) {
            throw new InvalidArgumentException('La venta no pertenece a la empresa indicada.');
        }

        $sale = $sale->fresh(['items', 'payments', 'creditMovements', 'customer.loyaltyAccount']);

        if (! $this->isEditableDraft($sale)) {
            throw new InvalidArgumentException('Solo se pueden editar ventas en borrador antes de confirmar el documento.');
        }

        $branch = $this->resolveBranch($company, (int) ($attributes['branch_id'] ?? 0));
        $warehouse = $this->resolveWarehouse($company, $branch, (int) ($attributes['warehouse_id'] ?? 0));
        $cashRegister = $this->resolveCashRegister($company, $branch, $attributes['cash_register_id'] ?? null);
        $user = $this->resolveUser($company, $attributes['user_id'] ?? null);
        $saleType = $this->blankToDefault($attributes['sale_type'] ?? null, 'pos');
        $customer = $this->resolveCustomer($company, $attributes['customer_id'] ?? null);
        $creditAccount = $this->resolveCreditAccountForSale($company, $saleType, $customer);
        $loyaltyAccount = $this->resolveLoyaltyAccountForSale($company, $customer);
        $items = $attributes['items'] ?? [];

        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('La venta debe incluir al menos una linea.');
        }

        $this->ensureManualDiscountsAllowed($company, $items);

        $resolvedItems = collect($items)
            ->map(function (array $item) use ($company) {
                $product = $this->resolveProduct($company, (int) ($item['product_id'] ?? 0));
                $presentation = $this->resolvePresentation($company, $product, $item['product_presentation_id'] ?? null);
                $variant = $this->resolveVariant($company, $product, $item['product_variant_id'] ?? null);

                return [
                    'product' => $product,
                    'presentation' => $presentation,
                    'variant' => $variant,
                    'quantity' => $item['quantity'] ?? 0,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_rate' => $item['tax_rate'] ?? 0,
                ];
            })
            ->all();

        $promotionPayload = $this->promotionEngine->apply($company, $resolvedItems, $attributes['sold_at'] ?? null);
        $calculatedLines = $this->calculateLines($promotionPayload['items']);
        $totals = $this->saleCalculator->calculateTotals($calculatedLines);
        $loyaltyRedemption = $this->resolveDraftLoyaltyRedemption(
            $company,
            $loyaltyAccount,
            $attributes['loyalty_points_to_redeem'] ?? null,
            $calculatedLines,
            $totals,
            $attributes['status'] ?? SaleStatus::Draft->value,
        );

        if ($loyaltyRedemption !== null) {
            $promotionPayload['items'] = collect($promotionPayload['items'])
                ->map(function (array $item, int $index) use ($loyaltyRedemption) {
                    $item['loyalty_discount_amount'] = $loyaltyRedemption['line_discounts'][$index];

                    return $item;
                })
                ->all();

            $calculatedLines = $this->calculateLines($promotionPayload['items']);
            $totals = $this->saleCalculator->calculateTotals($calculatedLines);
        }

        if (($attributes['status'] ?? SaleStatus::Draft->value) === SaleStatus::Confirmed->value) {
            $this->companyOperationalLimitGuard->ensureCanCreateConfirmedSale($company, $attributes['sold_at'] ?? null);
        }

        return DB::transaction(function () use ($company, $sale, $branch, $warehouse, $cashRegister, $user, $saleType, $customer, $creditAccount, $loyaltyAccount, $attributes, $calculatedLines, $totals, $promotionPayload, $loyaltyRedemption) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items', 'payments', 'creditMovements', 'customer.loyaltyAccount'])
                ->findOrFail($sale->id);

            if (! $this->isEditableDraft($sale)) {
                throw new InvalidArgumentException('Solo se pueden editar ventas en borrador antes de confirmar el documento.');
            }

            $beforeSale = $sale->fresh();

            $sale->update([
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'cash_register_id' => $cashRegister?->id,
                'customer_id' => $customer?->id,
                'credit_account_id' => $creditAccount?->id,
                'user_id' => $user?->id,
                'sale_type' => $saleType,
                'status' => $attributes['status'] ?? SaleStatus::Draft->value,
                'pricing_snapshot' => $this->buildPricingSnapshot($attributes['pricing_snapshot'] ?? null, $promotionPayload['summary'], $loyaltyRedemption),
                'notes' => $this->blankToNull($attributes['notes'] ?? null),
                'sold_at' => $attributes['sold_at'] ?? null,
                'credit_due_at' => $saleType === 'credit'
                    ? now()->addDays((int) $this->companySettings->get($company, 'credit', 'default_term_days'))
                    : null,
                ...$totals,
            ]);

            $sale->items()->delete();
            $sale->items()->createMany($calculatedLines);

            if ($this->shouldPostToInventory($sale->status)) {
                $sale = $this->postSaleToInventory->handle($sale);
            }

            if ($saleType === 'credit' && $sale->status === SaleStatus::Confirmed->value) {
                $this->creditLedger->recordSaleCharge($creditAccount, $sale, (string) $sale->grand_total);
            }

            if ($loyaltyAccount && $sale->status === SaleStatus::Confirmed->value) {
                if ($loyaltyRedemption !== null) {
                    $this->loyaltyLedger->redeemForSale($company, $loyaltyAccount, $sale, $loyaltyRedemption['points']);
                }

                $this->loyaltyLedger->awardForSale($company, $loyaltyAccount, $sale);
            }

            $sale = $sale->fresh(['items.product', 'items.presentation', 'items.variant', 'branch', 'warehouse', 'cashRegister', 'user', 'customer.person', 'creditAccount', 'payments']);
            $this->auditLogger->logUpdated($company, 'sale.draft_updated', $beforeSale, $sale, $user);

            return $sale;
        });
    }

    protected function isEditableDraft(Sale $sale): bool
    {
        return $sale->status === SaleStatus::Draft->value
            && $sale->posted_to_inventory_at === null
            && $sale->cancelled_at === null
            && $sale->returned_at === null
            && $sale->payments->isEmpty()
            && $sale->creditMovements->isEmpty()
            && $sale->items->every(fn ($item) => bccomp((string) $item->returned_quantity, '0', 6) === 0);
    }

    protected function calculateLines(array $items): array
    {
        return collect($items)
            ->map(function (array $item) {
                $item['product']->setAttribute('__promotion_snapshot', $item['promotion_snapshot'] !== [] ? $item['promotion_snapshot'] : null);

                $line = $this->saleCalculator->calculateLine(
                    $item['product'],
                    $item['presentation'],
                    $item['variant'],
                    $item['quantity'],
                    $item['unit_price'],
                    bcadd(
                        bcadd((string) $item['discount_amount'], (string) $item['promotion_discount_amount'], 2),
                        (string) ($item['loyalty_discount_amount'] ?? '0.00'),
                        2,
                    ),
                    $item['tax_rate'],
                );

                // IVA propio del producto (incluido en su costo/precio), distinto
                // del `tax_rate` aditivo de la linea: solo se usa para desglosar
                // el ticket impreso, ver BuildSaleTicketViewData.
                $line['product_tax_rate'] = (string) $item['product']->tax_rate;

                return $line;
            })
            ->all();
    }

    protected function resolveDraftLoyaltyRedemption(
        Company $company,
        ?LoyaltyAccount $loyaltyAccount,
        mixed $requestedPoints,
        array $calculatedLines,
        array $totals,
        string $status,
    ): ?array {
        if ($status !== SaleStatus::Confirmed->value) {
            return null;
        }

        return $this->resolveLoyaltyRedemption($company, $loyaltyAccount, $requestedPoints, $calculatedLines, $totals);
    }

    protected function resolveLoyaltyRedemption(
        Company $company,
        ?LoyaltyAccount $loyaltyAccount,
        mixed $requestedPoints,
        array $calculatedLines,
        array $totals,
    ): ?array {
        if ($requestedPoints === null || trim((string) $requestedPoints) === '') {
            return null;
        }

        $requestedPoints = bcadd(trim((string) $requestedPoints), '0', 4);

        if (bccomp($requestedPoints, '0.0000', 4) !== 1) {
            return null;
        }

        if (! $this->companyPlanResolver->hasFeature($company, 'loyalty.enabled')) {
            throw new InvalidArgumentException('El plan actual no tiene habilitada la feature de fidelizacion.');
        }

        if (! $loyaltyAccount) {
            throw new InvalidArgumentException('La redencion de puntos requiere un cliente con cuenta de fidelizacion activa.');
        }

        if (bccomp((string) $totals['grand_total'], '0.00', 2) !== 1) {
            throw new InvalidArgumentException('La venta no tiene saldo suficiente para aplicar una redencion de puntos.');
        }

        if (bccomp($requestedPoints, (string) $loyaltyAccount->points_balance, 4) === 1) {
            throw new InvalidArgumentException('La cuenta de fidelizacion no tiene puntos suficientes para esta redencion.');
        }

        $requestedCashEquivalent = $this->loyaltyLedger->calculateCashEquivalentForPoints($company, $requestedPoints);
        $appliedCashEquivalent = bccomp($requestedCashEquivalent, (string) $totals['grand_total'], 2) === 1
            ? (string) $totals['grand_total']
            : $requestedCashEquivalent;
        $appliedPoints = $this->loyaltyLedger->calculatePointsForCashEquivalent($company, $appliedCashEquivalent);

        if (bccomp($appliedPoints, '0.0000', 4) <= 0 || bccomp($appliedCashEquivalent, '0.00', 2) <= 0) {
            return null;
        }

        return [
            'points' => $appliedPoints,
            'cash_equivalent' => $appliedCashEquivalent,
            'line_discounts' => $this->allocateLoyaltyDiscountAcrossLines($calculatedLines, $appliedCashEquivalent),
            'rate' => (string) $this->companySettings->get($company, 'loyalty', 'points_rate'),
            'rule_type' => (string) $this->companySettings->get($company, 'loyalty', 'points_rule_type'),
        ];
    }

    protected function allocateLoyaltyDiscountAcrossLines(array $calculatedLines, string $cashEquivalent): array
    {
        $bases = collect($calculatedLines)->map(function (array $line) {
            return bcsub((string) $line['line_subtotal'], (string) $line['discount_amount'], 2);
        });

        $totalBase = $bases->reduce(fn (string $carry, string $value) => bcadd($carry, $value, 2), '0.00');

        if (bccomp($totalBase, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('No se puede distribuir la redencion de puntos sobre lineas sin base gravable.');
        }

        $positiveIndexes = $bases->keys()->filter(fn (int $index) => bccomp($bases[$index], '0.00', 2) === 1)->values()->all();
        $remaining = $cashEquivalent;
        $allocations = array_fill(0, count($calculatedLines), '0.00');

        foreach ($positiveIndexes as $position => $index) {
            if ($position === array_key_last($positiveIndexes)) {
                $allocations[$index] = $remaining;
                break;
            }

            $raw = bcdiv(bcmul($cashEquivalent, $bases[$index], 6), $totalBase, 6);
            $allocated = $this->roundMoney($raw);

            if (bccomp($allocated, $remaining, 2) === 1) {
                $allocated = $remaining;
            }

            $allocations[$index] = $allocated;
            $remaining = bcsub($remaining, $allocated, 2);
        }

        return $allocations;
    }

    protected function buildPricingSnapshot(?array $baseSnapshot, array $promotionSummary, ?array $loyaltyRedemption): array
    {
        $snapshot = $baseSnapshot ?? [];
        $snapshot['promotions'] = $promotionSummary;

        if ($loyaltyRedemption !== null) {
            $snapshot['loyalty_redemption'] = Arr::only($loyaltyRedemption, [
                'points',
                'cash_equivalent',
                'rate',
                'rule_type',
            ]);
        }

        return $snapshot;
    }

    protected function ensureManualDiscountsAllowed(Company $company, array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (
                $this->manualDiscountIsPositive($item['discount_amount'] ?? null)
                && ! $this->companyPlanResolver->hasFeature($company, 'pos.manual_discounts')
            ) {
                throw new InvalidArgumentException('El plan actual no permite descuentos manuales en la venta.');
            }
        }

        if ($this->companySettings->get($company, 'pos', 'allow_manual_discounts')) {
            return;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($this->manualDiscountIsPositive($item['discount_amount'] ?? null)) {
                throw new InvalidArgumentException('La empresa no permite descuentos manuales en la venta.');
            }
        }
    }

    protected function shouldPostToInventory(string $status): bool
    {
        return $status === SaleStatus::Confirmed->value;
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

    protected function resolveCashRegister(Company $company, Branch $branch, mixed $cashRegisterId): ?CashRegister
    {
        if (! $cashRegisterId) {
            return null;
        }

        return CashRegister::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->findOrFail((int) $cashRegisterId);
    }

    protected function resolveUser(Company $company, mixed $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        return User::query()
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $company->id))
            ->findOrFail((int) $userId);
    }

    protected function resolveCustomer(Company $company, mixed $customerId): ?Customer
    {
        if (! $customerId) {
            return null;
        }

        return Customer::query()
            ->where('company_id', $company->id)
            ->findOrFail((int) $customerId);
    }

    protected function resolveCreditAccountForSale(Company $company, string $saleType, ?Customer $customer): ?CreditAccount
    {
        if ($saleType !== 'credit') {
            return null;
        }

        if (! $this->companySettings->get($company, 'credit', 'credit_enabled')) {
            throw new InvalidArgumentException('La empresa no tiene habilitado el modulo de credito.');
        }

        if ($this->companySettings->get($company, 'pos', 'require_customer_for_credit_sale') && ! $customer) {
            throw new InvalidArgumentException('La venta a credito requiere un cliente.');
        }

        if (! $customer) {
            throw new InvalidArgumentException('La venta a credito requiere un cliente.');
        }

        if (! $customer->credit_enabled) {
            throw new InvalidArgumentException('El cliente no tiene credito habilitado.');
        }

        $creditAccount = $customer->creditAccount()->first();

        if (! $creditAccount) {
            throw new InvalidArgumentException('El cliente no tiene una cuenta de credito creada.');
        }

        if ($creditAccount->status !== 'active') {
            throw new InvalidArgumentException('La cuenta de credito del cliente no esta activa.');
        }

        if (
            $this->companySettings->get($company, 'credit', 'block_new_credit_if_overdue')
            && $this->creditLedger->hasOverdueBalance($creditAccount)
        ) {
            throw new InvalidArgumentException('El cliente tiene cartera vencida y la empresa bloquea nuevos creditos.');
        }

        return $creditAccount;
    }

    protected function resolveLoyaltyAccountForSale(Company $company, ?Customer $customer): ?LoyaltyAccount
    {
        if (! $customer) {
            return null;
        }

        if (! $this->companyPlanResolver->hasFeature($company, 'loyalty.enabled')) {
            return null;
        }

        if (! $this->companySettings->get($company, 'loyalty', 'loyalty_enabled')) {
            return null;
        }

        if (! $customer->loyalty_enabled) {
            return null;
        }

        $loyaltyAccount = $customer->loyaltyAccount()->first();

        if (! $loyaltyAccount) {
            throw new InvalidArgumentException('El cliente no tiene una cuenta de fidelizacion creada.');
        }

        if ($loyaltyAccount->status !== 'active') {
            throw new InvalidArgumentException('La cuenta de fidelizacion del cliente no esta activa.');
        }

        return $loyaltyAccount;
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

    protected function roundMoney(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    protected function manualDiscountIsPositive(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $value = trim((string) $value);

        if ($value === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return false;
        }

        return bccomp(bcadd($value, '0', 2), '0', 2) === 1;
    }
}
