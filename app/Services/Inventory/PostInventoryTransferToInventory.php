<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostInventoryTransferToInventory
{
    public function handle(InventoryTransfer $transfer): InventoryTransfer
    {
        $transfer->loadMissing(['items.product', 'sourceWarehouse', 'destinationWarehouse']);

        if ($transfer->posted_to_inventory_at !== null) {
            return $transfer->fresh(['items.product', 'sourceWarehouse', 'destinationWarehouse']);
        }

        return DB::transaction(function () use ($transfer) {
            $transfer = InventoryTransfer::query()
                ->lockForUpdate()
                ->with(['items.product', 'sourceWarehouse', 'destinationWarehouse'])
                ->findOrFail($transfer->id);

            if ($transfer->posted_to_inventory_at !== null) {
                return $transfer->fresh(['items.product', 'sourceWarehouse', 'destinationWarehouse']);
            }

            foreach ($transfer->items as $item) {
                $this->postItem($transfer, $item);
            }

            $transfer->update([
                'posted_to_inventory_at' => now(),
            ]);

            return $transfer->fresh(['items.product', 'sourceWarehouse', 'destinationWarehouse']);
        });
    }

    protected function postItem(InventoryTransfer $transfer, InventoryTransferItem $item): void
    {
        if (! $item->product->tracks_inventory) {
            return;
        }

        $sourceBalance = InventoryBalance::query()
            ->where('company_id', $transfer->company_id)
            ->where('warehouse_id', $transfer->source_warehouse_id)
            ->where('product_id', $item->product_id)
            ->when(
                $item->product_variant_id,
                fn ($query) => $query->where('product_variant_id', $item->product_variant_id),
                fn ($query) => $query->whereNull('product_variant_id')
            )
            ->lockForUpdate()
            ->first();

        $destinationBalance = InventoryBalance::query()
            ->where('company_id', $transfer->company_id)
            ->where('warehouse_id', $transfer->destination_warehouse_id)
            ->where('product_id', $item->product_id)
            ->when(
                $item->product_variant_id,
                fn ($query) => $query->where('product_variant_id', $item->product_variant_id),
                fn ($query) => $query->whereNull('product_variant_id')
            )
            ->lockForUpdate()
            ->first();

        $sourceQuantity = $sourceBalance?->quantity_on_hand ?? '0.000000';
        $sourceAverageCost = $sourceBalance?->average_cost ?? '0.0000';
        $transferQuantity = (string) $item->quantity;

        if (bccomp($sourceQuantity, $transferQuantity, 6) < 0) {
            throw new InvalidArgumentException('El traslado no tiene stock suficiente en la bodega origen.');
        }

        $movementUnitCost = $sourceAverageCost;
        $newSourceQuantity = bcsub($sourceQuantity, $transferQuantity, 6);
        $newSourceAverageCost = bccomp($newSourceQuantity, '0', 6) === 0
            ? '0.0000'
            : $sourceAverageCost;

        InventoryMovement::query()->create([
            'company_id' => $transfer->company_id,
            'warehouse_id' => $transfer->source_warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => InventoryMovementType::TransferOut->value,
            'reference_type' => InventoryTransferItem::class,
            'reference_id' => $item->id,
            'quantity_in' => '0.000000',
            'quantity_out' => $transferQuantity,
            'unit_cost' => $movementUnitCost,
            'balance_quantity' => $newSourceQuantity,
            'balance_cost' => $newSourceAverageCost,
            'occurred_at' => $transfer->transferred_at ?? now(),
        ]);

        $sourceBalance?->update([
            'quantity_on_hand' => $newSourceQuantity,
            'average_cost' => $newSourceAverageCost,
        ]);

        $destinationQuantity = $destinationBalance?->quantity_on_hand ?? '0.000000';
        $destinationAverageCost = $destinationBalance?->average_cost ?? '0.0000';
        $newDestinationQuantity = bcadd($destinationQuantity, $transferQuantity, 6);
        $newDestinationAverageCost = $this->weightedAverage(
            $destinationQuantity,
            $destinationAverageCost,
            $transferQuantity,
            $movementUnitCost,
        );

        InventoryMovement::query()->create([
            'company_id' => $transfer->company_id,
            'warehouse_id' => $transfer->destination_warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => InventoryMovementType::TransferIn->value,
            'reference_type' => InventoryTransferItem::class,
            'reference_id' => $item->id,
            'quantity_in' => $transferQuantity,
            'quantity_out' => '0.000000',
            'unit_cost' => $movementUnitCost,
            'balance_quantity' => $newDestinationQuantity,
            'balance_cost' => $newDestinationAverageCost,
            'occurred_at' => $transfer->transferred_at ?? now(),
        ]);

        if ($destinationBalance) {
            $destinationBalance->update([
                'quantity_on_hand' => $newDestinationQuantity,
                'average_cost' => $newDestinationAverageCost,
            ]);

            return;
        }

        InventoryBalance::query()->create([
            'company_id' => $transfer->company_id,
            'warehouse_id' => $transfer->destination_warehouse_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity_on_hand' => $newDestinationQuantity,
            'average_cost' => $newDestinationAverageCost,
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
