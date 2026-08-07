<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\SaleStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostSaleToInventory
{
    public function __construct(
        protected CompanySettings $companySettings,
    ) {
    }

    public function handle(Sale $sale): Sale
    {
        $sale->loadMissing(['items.product', 'warehouse']);

        if (! $this->shouldPost($sale)) {
            throw new InvalidArgumentException('Solo se pueden postear a inventario ventas confirmadas.');
        }

        if ($sale->posted_to_inventory_at !== null) {
            return $sale->fresh(['items.product', 'warehouse']);
        }

        return DB::transaction(function () use ($sale) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items.product', 'warehouse'])
                ->findOrFail($sale->id);

            if ($sale->posted_to_inventory_at !== null) {
                return $sale->fresh(['items.product', 'warehouse']);
            }

            foreach ($sale->items as $item) {
                $this->postItem($sale, $item);
            }

            $sale->update([
                'posted_to_inventory_at' => now(),
            ]);

            return $sale->fresh(['items.product', 'warehouse']);
        });
    }

    protected function postItem(Sale $sale, SaleItem $item): void
    {
        if (! $item->product->tracks_inventory) {
            return;
        }

        $balance = InventoryBalance::query()
            ->where('company_id', $sale->company_id)
            ->where('warehouse_id', $sale->warehouse_id)
            ->where('product_id', $item->product_id)
            ->when(
                $item->product_variant_id,
                fn ($query) => $query->where('product_variant_id', $item->product_variant_id),
                fn ($query) => $query->whereNull('product_variant_id')
            )
            ->lockForUpdate()
            ->first();

        $currentQuantity = $balance?->quantity_on_hand ?? '0.000000';
        $currentAverageCost = $balance?->average_cost ?? '0.0000';
        $outgoingQuantity = (string) $item->base_quantity;
        $allowNegativeStock = (bool) $this->companySettings->get($sale->company_id, 'pos', 'allow_negative_stock');

        if (! $allowNegativeStock && bccomp($currentQuantity, $outgoingQuantity, 6) < 0) {
            throw new InvalidArgumentException('La venta no tiene stock suficiente para completar la salida.');
        }

        $newQuantity = bcsub($currentQuantity, $outgoingQuantity, 6);
        $movementUnitCost = $this->resolveMovementUnitCost($item, $currentAverageCost);
        $newAverageCost = bccomp($newQuantity, '0', 6) === 0
            ? '0.0000'
            : $movementUnitCost;

        InventoryMovement::query()->create([
            'company_id' => $sale->company_id,
            'warehouse_id' => $sale->warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => InventoryMovementType::SaleOut->value,
            'reference_type' => SaleItem::class,
            'reference_id' => $item->id,
            'quantity_in' => '0.000000',
            'quantity_out' => $outgoingQuantity,
            'unit_cost' => $movementUnitCost,
            'balance_quantity' => $newQuantity,
            'balance_cost' => $newAverageCost,
            'occurred_at' => $sale->sold_at ?? now(),
        ]);

        if ($balance) {
            $balance->update([
                'quantity_on_hand' => $newQuantity,
                'average_cost' => $newAverageCost,
            ]);
        } else {
            InventoryBalance::query()->create([
                'company_id' => $sale->company_id,
                'warehouse_id' => $sale->warehouse_id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity_on_hand' => $newQuantity,
                'average_cost' => $newAverageCost,
            ]);
        }

        $item->update([
            'cost_snapshot' => $movementUnitCost,
        ]);
    }

    protected function shouldPost(Sale $sale): bool
    {
        return $sale->status === SaleStatus::Confirmed->value;
    }

    protected function resolveMovementUnitCost(SaleItem $item, string $currentAverageCost): string
    {
        if (bccomp($currentAverageCost, '0', 4) === 1) {
            return $currentAverageCost;
        }

        return bcadd((string) ($item->product->cost ?? '0'), '0', 4);
    }
}
