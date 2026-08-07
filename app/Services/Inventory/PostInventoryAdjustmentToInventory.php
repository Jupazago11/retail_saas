<?php

namespace App\Services\Inventory;

use App\Enums\InventoryAdjustmentType;
use App\Enums\InventoryMovementType;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;

class PostInventoryAdjustmentToInventory
{
    public function handle(InventoryAdjustment $adjustment): InventoryAdjustment
    {
        $adjustment->loadMissing(['items.product', 'warehouse']);

        if ($adjustment->posted_to_inventory_at !== null) {
            return $adjustment->fresh(['items.product', 'warehouse']);
        }

        return DB::transaction(function () use ($adjustment) {
            $adjustment = InventoryAdjustment::query()
                ->lockForUpdate()
                ->with(['items.product', 'warehouse'])
                ->findOrFail($adjustment->id);

            if ($adjustment->posted_to_inventory_at !== null) {
                return $adjustment->fresh(['items.product', 'warehouse']);
            }

            $adjustmentType = InventoryAdjustmentType::from($adjustment->adjustment_type);

            foreach ($adjustment->items as $item) {
                $this->postItem($adjustment, $item, $adjustmentType);
            }

            $adjustment->update([
                'posted_to_inventory_at' => now(),
            ]);

            return $adjustment->fresh(['items.product', 'warehouse']);
        });
    }

    protected function postItem(
        InventoryAdjustment $adjustment,
        InventoryAdjustmentItem $item,
        InventoryAdjustmentType $adjustmentType,
    ): void {
        if (! $item->product->tracks_inventory) {
            return;
        }

        $balance = InventoryBalance::query()
            ->where('company_id', $adjustment->company_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->where('product_id', $item->product_id)
            ->when(
                $item->product_variant_id,
                fn ($query) => $query->where('product_variant_id', $item->product_variant_id),
                fn ($query) => $query->whereNull('product_variant_id')
            )
            ->lockForUpdate()
            ->first();

        if ($adjustmentType === InventoryAdjustmentType::Increase) {
            $this->postIncrease($adjustment, $item, $balance);

            return;
        }

        $this->postDecrease($adjustment, $item, $balance);
    }

    protected function postIncrease(
        InventoryAdjustment $adjustment,
        InventoryAdjustmentItem $item,
        ?InventoryBalance $balance,
    ): void {
        $currentQuantity = $balance?->quantity_on_hand ?? '0.000000';
        $currentAverageCost = $balance?->average_cost ?? '0.0000';
        $incomingQuantity = (string) $item->quantity;
        $incomingUnitCost = (string) $item->unit_cost;
        $newQuantity = bcadd($currentQuantity, $incomingQuantity, 6);
        $newAverageCost = $this->weightedAverage(
            $currentQuantity,
            $currentAverageCost,
            $incomingQuantity,
            $incomingUnitCost,
        );

        InventoryMovement::query()->create([
            'company_id' => $adjustment->company_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => InventoryMovementType::AdjustmentIn->value,
            'reference_type' => InventoryAdjustmentItem::class,
            'reference_id' => $item->id,
            'quantity_in' => $incomingQuantity,
            'quantity_out' => '0.000000',
            'unit_cost' => $incomingUnitCost,
            'balance_quantity' => $newQuantity,
            'balance_cost' => $newAverageCost,
            'occurred_at' => $adjustment->adjusted_at ?? now(),
        ]);

        if ($balance) {
            $balance->update([
                'quantity_on_hand' => $newQuantity,
                'average_cost' => $newAverageCost,
            ]);

            return;
        }

        InventoryBalance::query()->create([
            'company_id' => $adjustment->company_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity_on_hand' => $newQuantity,
            'average_cost' => $newAverageCost,
        ]);
    }

    protected function postDecrease(
        InventoryAdjustment $adjustment,
        InventoryAdjustmentItem $item,
        ?InventoryBalance $balance,
    ): void {
        $currentQuantity = $balance?->quantity_on_hand ?? '0.000000';
        $currentAverageCost = $balance?->average_cost ?? '0.0000';
        $outgoingQuantity = (string) $item->quantity;

        if (bccomp($currentQuantity, $outgoingQuantity, 6) < 0) {
            throw new \InvalidArgumentException('El ajuste no tiene stock suficiente para descontar la cantidad indicada.');
        }

        $newQuantity = bcsub($currentQuantity, $outgoingQuantity, 6);
        $movementUnitCost = bccomp($currentAverageCost, '0', 4) > 0
            ? $currentAverageCost
            : (string) $item->unit_cost;
        $newAverageCost = bccomp($newQuantity, '0', 6) === 0
            ? '0.0000'
            : $currentAverageCost;

        InventoryMovement::query()->create([
            'company_id' => $adjustment->company_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => InventoryMovementType::AdjustmentOut->value,
            'reference_type' => InventoryAdjustmentItem::class,
            'reference_id' => $item->id,
            'quantity_in' => '0.000000',
            'quantity_out' => $outgoingQuantity,
            'unit_cost' => $movementUnitCost,
            'balance_quantity' => $newQuantity,
            'balance_cost' => $newAverageCost,
            'occurred_at' => $adjustment->adjusted_at ?? now(),
        ]);

        $balance?->update([
            'quantity_on_hand' => $newQuantity,
            'average_cost' => $newAverageCost,
        ]);
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
}
