<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReturnPurchaseFromInventory
{
    public function handle(Purchase $purchase): Purchase
    {
        $purchase->loadMissing(['items.product', 'warehouse']);

        if ($purchase->posted_to_inventory_at === null) {
            throw new InvalidArgumentException('Solo se pueden devolver compras ya aplicadas a inventario.');
        }

        if ($purchase->returned_from_inventory_at !== null) {
            return $purchase->fresh(['items.product', 'warehouse']);
        }

        return DB::transaction(function () use ($purchase) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->with(['items.product', 'warehouse'])
                ->findOrFail($purchase->id);

            if ($purchase->posted_to_inventory_at === null) {
                throw new InvalidArgumentException('Solo se pueden devolver compras ya aplicadas a inventario.');
            }

            if ($purchase->returned_from_inventory_at !== null) {
                return $purchase->fresh(['items.product', 'warehouse']);
            }

            foreach ($purchase->items as $item) {
                $this->returnItem($purchase, $item);
            }

            $purchase->update([
                'status' => PurchaseStatus::Returned->value,
                'returned_from_inventory_at' => now(),
            ]);

            return $purchase->fresh(['items.product', 'warehouse']);
        });
    }

    protected function returnItem(Purchase $purchase, PurchaseItem $item): void
    {
        if (! $item->product->tracks_inventory) {
            return;
        }

        $balance = InventoryBalance::query()
            ->where('company_id', $purchase->company_id)
            ->where('warehouse_id', $purchase->warehouse_id)
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
        $returnQuantity = (string) $item->base_quantity;

        if (bccomp($currentQuantity, $returnQuantity, 6) < 0) {
            throw new InvalidArgumentException('La devolucion no tiene stock suficiente para revertir la compra.');
        }

        $newQuantity = bcsub($currentQuantity, $returnQuantity, 6);
        $movementUnitCost = $this->outgoingUnitCost($item, $currentAverageCost);
        $newAverageCost = bccomp($newQuantity, '0', 6) === 0
            ? '0.0000'
            : $currentAverageCost;

        InventoryMovement::query()->create([
            'company_id' => $purchase->company_id,
            'warehouse_id' => $purchase->warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => InventoryMovementType::PurchaseReturnOut->value,
            'reference_type' => PurchaseItem::class,
            'reference_id' => $item->id,
            'quantity_in' => '0.000000',
            'quantity_out' => $returnQuantity,
            'unit_cost' => $movementUnitCost,
            'balance_quantity' => $newQuantity,
            'balance_cost' => $newAverageCost,
            'occurred_at' => now(),
        ]);

        $balance?->update([
            'quantity_on_hand' => $newQuantity,
            'average_cost' => $newAverageCost,
        ]);
    }

    protected function outgoingUnitCost(PurchaseItem $item, string $currentAverageCost): string
    {
        if (bccomp($currentAverageCost, '0', 4) > 0) {
            return $currentAverageCost;
        }

        return $this->baseUnitCost($item);
    }

    protected function baseUnitCost(PurchaseItem $item): string
    {
        return bcdiv((string) $item->line_subtotal, (string) $item->base_quantity, 4);
    }
}
