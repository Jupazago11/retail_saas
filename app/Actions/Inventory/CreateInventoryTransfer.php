<?php

namespace App\Actions\Inventory;

use App\Models\Company;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\PostInventoryTransferToInventory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateInventoryTransfer
{
    public function __construct(
        protected PostInventoryTransferToInventory $postInventoryTransferToInventory,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $attributes): InventoryTransfer
    {
        $sourceWarehouse = $this->resolveWarehouse($company, (int) ($attributes['source_warehouse_id'] ?? 0));
        $destinationWarehouse = $this->resolveWarehouse($company, (int) ($attributes['destination_warehouse_id'] ?? 0));
        $items = $attributes['items'] ?? [];

        if ($sourceWarehouse->id === $destinationWarehouse->id) {
            throw new InvalidArgumentException('El traslado requiere bodegas origen y destino distintas.');
        }

        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('El traslado debe incluir al menos una linea.');
        }

        $normalizedItems = collect($items)
            ->map(function (array $item) use ($company) {
                $product = $this->resolveProduct($company, (int) ($item['product_id'] ?? 0));
                $variant = $this->resolveVariant($company, $product, $item['product_variant_id'] ?? null);
                $quantity = $this->normalizePositiveDecimal($item['quantity'] ?? 0);

                return [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $quantity,
                ];
            })
            ->all();

        return DB::transaction(function () use ($company, $sourceWarehouse, $destinationWarehouse, $attributes, $normalizedItems) {
            $transfer = InventoryTransfer::query()->create([
                'company_id' => $company->id,
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_warehouse_id' => $destinationWarehouse->id,
                'reason' => $this->normalizeReason($attributes['reason'] ?? null),
                'notes' => $this->blankToNull($attributes['notes'] ?? null),
                'transferred_at' => $attributes['transferred_at'] ?? null,
            ]);

            $transfer->items()->createMany($normalizedItems);
            $transfer = $this->postInventoryTransferToInventory->handle($transfer);

            $transfer = $transfer->fresh(['items.product', 'items.variant', 'sourceWarehouse', 'destinationWarehouse']);
            $this->auditLogger->logCreated($company, 'inventory.transfer.created', $transfer);

            return $transfer;
        });
    }

    protected function resolveWarehouse(Company $company, int $warehouseId): Warehouse
    {
        return Warehouse::query()
            ->where('company_id', $company->id)
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

    protected function normalizePositiveDecimal(mixed $value): string
    {
        $normalized = number_format((float) $value, 6, '.', '');

        if (bccomp($normalized, '0', 6) <= 0) {
            throw new InvalidArgumentException('La cantidad del traslado debe ser mayor a cero.');
        }

        return $normalized;
    }

    protected function normalizeReason(mixed $value): string
    {
        $reason = $this->blankToNull($value);

        if ($reason === null) {
            throw new InvalidArgumentException('El traslado debe incluir un motivo.');
        }

        return $reason;
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
