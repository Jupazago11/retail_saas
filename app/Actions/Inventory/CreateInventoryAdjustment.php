<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryAdjustmentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\PostInventoryAdjustmentToInventory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateInventoryAdjustment
{
    public function __construct(
        protected PostInventoryAdjustmentToInventory $postInventoryAdjustmentToInventory,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $attributes): InventoryAdjustment
    {
        $branch = $this->resolveBranch($company, (int) ($attributes['branch_id'] ?? 0));
        $warehouse = $this->resolveWarehouse($company, $branch, (int) ($attributes['warehouse_id'] ?? 0));
        $adjustmentType = $this->resolveAdjustmentType($attributes['adjustment_type'] ?? null);
        $items = $attributes['items'] ?? [];

        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('El ajuste debe incluir al menos una linea.');
        }

        $normalizedItems = collect($items)
            ->map(function (array $item) use ($company, $adjustmentType) {
                $product = $this->resolveProduct($company, (int) ($item['product_id'] ?? 0));
                $variant = $this->resolveVariant($company, $product, $item['product_variant_id'] ?? null);
                $quantity = $this->normalizePositiveDecimal($item['quantity'] ?? 0, 'La cantidad del ajuste debe ser mayor a cero.');
                $unitCost = $this->normalizeUnitCost(
                    $item['unit_cost'] ?? 0,
                    $adjustmentType,
                );

                return [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ];
            })
            ->all();

        return DB::transaction(function () use ($company, $branch, $warehouse, $attributes, $adjustmentType, $normalizedItems) {
            $adjustment = InventoryAdjustment::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'adjustment_type' => $adjustmentType->value,
                'reason' => $this->normalizeReason($attributes['reason'] ?? null),
                'notes' => $this->blankToNull($attributes['notes'] ?? null),
                'adjusted_at' => $attributes['adjusted_at'] ?? null,
            ]);

            $adjustment->items()->createMany($normalizedItems);
            $adjustment = $this->postInventoryAdjustmentToInventory->handle($adjustment);

            $adjustment = $adjustment->fresh(['items.product', 'items.variant', 'branch', 'warehouse']);
            $this->auditLogger->logCreated($company, 'inventory.adjustment.created', $adjustment);

            return $adjustment;
        });
    }

    protected function resolveAdjustmentType(mixed $value): InventoryAdjustmentType
    {
        $type = InventoryAdjustmentType::tryFrom((string) $value);

        if ($type === null) {
            throw new InvalidArgumentException('El tipo de ajuste es invalido.');
        }

        return $type;
    }

    protected function normalizePositiveDecimal(mixed $value, string $message): string
    {
        $normalized = number_format((float) $value, 6, '.', '');

        if (bccomp($normalized, '0', 6) <= 0) {
            throw new InvalidArgumentException($message);
        }

        return $normalized;
    }

    protected function normalizeUnitCost(mixed $value, InventoryAdjustmentType $adjustmentType): string
    {
        $normalized = number_format((float) $value, 4, '.', '');

        if ($adjustmentType === InventoryAdjustmentType::Increase && bccomp($normalized, '0', 4) <= 0) {
            throw new InvalidArgumentException('Las entradas por ajuste requieren un costo unitario mayor a cero.');
        }

        if (bccomp($normalized, '0', 4) < 0) {
            throw new InvalidArgumentException('El costo unitario del ajuste no puede ser negativo.');
        }

        return $normalized;
    }

    protected function normalizeReason(mixed $value): string
    {
        $reason = $this->blankToNull($value);

        if ($reason === null) {
            throw new InvalidArgumentException('El ajuste debe incluir un motivo.');
        }

        return $reason;
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
}
