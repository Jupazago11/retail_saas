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

class PostPurchaseToInventory
{
    public function handle(Purchase $purchase): Purchase
    {
        $purchase->loadMissing(['items.product', 'warehouse']);

        if (! $this->shouldPost($purchase)) {
            throw new InvalidArgumentException('Solo se pueden postear a inventario compras confirmadas o pagadas.');
        }

        if ($purchase->posted_to_inventory_at !== null) {
            return $purchase->fresh(['items.product', 'warehouse']);
        }

        return DB::transaction(function () use ($purchase) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->with(['items.product', 'warehouse'])
                ->findOrFail($purchase->id);

            if ($purchase->posted_to_inventory_at !== null) {
                return $purchase->fresh(['items.product', 'warehouse']);
            }

            foreach ($purchase->items as $item) {
                $this->postItem($purchase, $item);
            }

            $purchase->update([
                'posted_to_inventory_at' => now(),
            ]);

            return $purchase->fresh(['items.product', 'warehouse']);
        });
    }

    protected function postItem(Purchase $purchase, PurchaseItem $item): void
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
        $incomingQuantity = (string) $item->base_quantity;
        $incomingUnitCost = $this->baseUnitCost($item);
        $newQuantity = bcadd($currentQuantity, $incomingQuantity, 6);
        $newAverageCost = $this->weightedAverage(
            $currentQuantity,
            $currentAverageCost,
            $incomingQuantity,
            $incomingUnitCost,
        );

        InventoryMovement::query()->create([
            'company_id' => $purchase->company_id,
            'warehouse_id' => $purchase->warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => InventoryMovementType::PurchaseIn->value,
            'reference_type' => PurchaseItem::class,
            'reference_id' => $item->id,
            'quantity_in' => $incomingQuantity,
            'quantity_out' => '0.000000',
            'unit_cost' => $incomingUnitCost,
            'balance_quantity' => $newQuantity,
            'balance_cost' => $newAverageCost,
            'occurred_at' => $purchase->purchased_at ?? now(),
        ]);

        if ($balance) {
            $balance->update([
                'quantity_on_hand' => $newQuantity,
                'average_cost' => $newAverageCost,
            ]);

            return;
        }

        InventoryBalance::query()->create([
            'company_id' => $purchase->company_id,
            'warehouse_id' => $purchase->warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity_on_hand' => $newQuantity,
            'average_cost' => $newAverageCost,
        ]);
    }

    protected function baseUnitCost(PurchaseItem $item): string
    {
        return bcdiv((string) $item->line_subtotal, (string) $item->base_quantity, 4);
    }

    protected function weightedAverage(
        string $currentQuantity,
        string $currentAverageCost,
        string $incomingQuantity,
        string $incomingUnitCost,
    ): string {
        $existingValue = bcmul($currentQuantity, $currentAverageCost, 8);
        $incomingValue = bcmul($incomingQuantity, $incomingUnitCost, 8);
        $newQuantity = bcadd($currentQuantity, $incomingQuantity, 6);

        if (bccomp($newQuantity, '0', 6) <= 0) {
            return '0.0000';
        }

        return bcdiv(bcadd($existingValue, $incomingValue, 8), $newQuantity, 4);
    }

    protected function shouldPost(Purchase $purchase): bool
    {
        return in_array($purchase->status, [
            PurchaseStatus::Confirmed->value,
            PurchaseStatus::PartiallyPaid->value,
            PurchaseStatus::Paid->value,
        ], true);
    }
}
