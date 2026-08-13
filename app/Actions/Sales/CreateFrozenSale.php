<?php

namespace App\Actions\Sales;

use App\Enums\FrozenSaleStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\FrozenSale;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Sales\SaleCalculator;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateFrozenSale
{
    // Regla de negocio fija: las ventas congeladas expiran a las 24 horas,
    // ya no es configurable por empresa.
    protected const EXPIRATION_MINUTES = 1440;

    public function __construct(
        protected SaleCalculator $saleCalculator,
        protected CompanySettings $companySettings,
        protected CompanyPlanResolver $companyPlanResolver,
    ) {
    }

    public function handle(Company $company, array $attributes): FrozenSale
    {
        $this->ensureFrozenSalesEnabled($company);

        $branch = $this->resolveBranch($company, (int) ($attributes['branch_id'] ?? 0));
        $warehouse = $this->resolveWarehouse($company, $branch, (int) ($attributes['warehouse_id'] ?? 0));
        $cashRegister = $this->resolveCashRegister($company, $branch, $attributes['cash_register_id'] ?? null);
        $creator = $this->resolveUser($company, (int) ($attributes['created_by'] ?? 0));
        $items = $attributes['items'] ?? [];

        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('La venta congelada debe incluir al menos una linea.');
        }

        $this->ensureManualDiscountsAllowed($company, $items);

        $calculatedLines = collect($items)
            ->map(function (array $item) use ($company) {
                $product = $this->resolveProduct($company, (int) ($item['product_id'] ?? 0));
                $presentation = $this->resolvePresentation($company, $product, $item['product_presentation_id'] ?? null);
                $variant = $this->resolveVariant($company, $product, $item['product_variant_id'] ?? null);

                return $this->saleCalculator->calculateLine(
                    $product,
                    $presentation,
                    $variant,
                    $item['quantity'] ?? 0,
                    $item['unit_price'] ?? 0,
                    $item['discount_amount'] ?? 0,
                    $item['tax_rate'] ?? 0,
                );
            })
            ->all();

        $totals = $this->saleCalculator->calculateTotals($calculatedLines);
        $expiresAt = now()->addMinutes(self::EXPIRATION_MINUTES);

        return DB::transaction(function () use ($company, $branch, $warehouse, $cashRegister, $creator, $attributes, $calculatedLines, $totals, $expiresAt) {
            $frozenSale = FrozenSale::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'cash_register_id' => $cashRegister?->id,
                'customer_id' => $attributes['customer_id'] ?? null,
                'created_by' => $creator->id,
                'label' => $this->normalizeLabel($attributes['label'] ?? null),
                'status' => FrozenSaleStatus::Open->value,
                'expires_at' => $expiresAt,
                'payload_snapshot' => [
                    'sale_type' => $this->blankToDefault($attributes['sale_type'] ?? null, 'pos'),
                    'pricing_snapshot' => $attributes['pricing_snapshot'] ?? null,
                    'notes' => $this->blankToNull($attributes['notes'] ?? null),
                    'items' => $calculatedLines,
                    'totals' => $totals,
                ],
            ]);

            return $frozenSale->fresh(['branch', 'warehouse', 'cashRegister', 'creator']);
        });
    }

    protected function ensureFrozenSalesEnabled(Company $company): void
    {
        if (! $this->companyPlanResolver->hasFeature($company, 'pos.frozen_sales')) {
            throw new InvalidArgumentException('El plan actual no tiene habilitada la feature de ventas congeladas.');
        }

        if (! $this->companySettings->get($company, 'pos', 'frozen_sales_enabled')) {
            throw new InvalidArgumentException('Las ventas congeladas estan deshabilitadas para esta empresa.');
        }
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
                throw new InvalidArgumentException('El plan actual no permite descuentos manuales en ventas congeladas.');
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
                throw new InvalidArgumentException('La empresa no permite descuentos manuales en ventas congeladas.');
            }
        }
    }

    protected function normalizeLabel(mixed $value): string
    {
        $label = $this->blankToNull($value);

        if ($label === null) {
            throw new InvalidArgumentException('La venta congelada requiere una etiqueta.');
        }

        return $label;
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

    protected function resolveUser(Company $company, int $userId): User
    {
        return User::query()
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $company->id))
            ->findOrFail($userId);
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
