<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\SaleStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Products\ProductPresentationConverter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReturnSaleToInventory
{
    public function __construct(
        protected ProductPresentationConverter $productPresentationConverter,
    ) {
    }

    public function handle(Sale $sale, array $items, ?SaleStatus $forcedStatus = null): Sale
    {
        $sale->loadMissing(['items.product', 'warehouse']);

        if ($sale->status !== SaleStatus::Confirmed->value && $sale->status !== SaleStatus::PartiallyReturned->value) {
            throw new InvalidArgumentException('Solo se pueden devolver ventas confirmadas o parcialmente devueltas.');
        }

        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('Debe indicar al menos una linea para devolver.');
        }

        return DB::transaction(function () use ($sale, $items, $forcedStatus) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items.product', 'warehouse'])
                ->findOrFail($sale->id);

            $saleItems = $sale->items->keyBy('id');
            $normalizedItems = collect($items)->map(function (array $item) use ($saleItems) {
                $saleItemId = (int) ($item['sale_item_id'] ?? 0);
                $saleItem = $saleItems->get($saleItemId);

                if (! $saleItem) {
                    throw new InvalidArgumentException('La linea indicada no pertenece a la venta.');
                }

                $quantity = $this->normalizePositiveDecimal($item['quantity'] ?? 0);
                $remainingQuantity = bcsub((string) $saleItem->quantity, (string) $saleItem->returned_quantity, 6);

                if (bccomp($quantity, $remainingQuantity, 6) === 1) {
                    throw new InvalidArgumentException('La cantidad devuelta supera el saldo pendiente de la linea.');
                }

                return [
                    'sale_item' => $saleItem,
                    'quantity' => $quantity,
                    'base_quantity' => $this->toBaseQuantity($saleItem, $quantity),
                ];
            });

            foreach ($normalizedItems as $item) {
                $this->returnItem($sale, $item['sale_item'], $item['quantity'], $item['base_quantity']);
            }

            $status = $forcedStatus?->value ?? $this->resolveStatusAfterReturn($sale->items->fresh());

            $sale->update([
                'status' => $status,
                'returned_at' => now(),
            ]);

            return $sale->fresh(['items', 'payments']);
        });
    }

    protected function returnItem(Sale $sale, SaleItem $saleItem, string $returnedQuantity, string $returnedBaseQuantity): void
    {
        if ($saleItem->product->is_recipe) {
            $this->returnRecipeItem($sale, $saleItem, $returnedBaseQuantity);
        } elseif ($saleItem->product->tracks_inventory) {
            $incomingUnitCost = (string) ($saleItem->cost_snapshot ?? '0.0000');
            $this->returnProductIn($sale, $saleItem->product_id, $saleItem->product_variant_id, $returnedBaseQuantity, $incomingUnitCost, $saleItem);
        }

        $saleItem->update([
            'returned_quantity' => bcadd((string) $saleItem->returned_quantity, $returnedQuantity, 6),
            'returned_base_quantity' => bcadd((string) $saleItem->returned_base_quantity, $returnedBaseQuantity, 6),
        ]);
    }

    /**
     * Espejo de PostSaleToInventory::postRecipeItem(): un plato con receta no
     * tiene stock propio, asi que devolverlo restituye cada insumo de su
     * receta en vez del plato.
     */
    protected function returnRecipeItem(Sale $sale, SaleItem $saleItem, string $returnedBaseQuantity): void
    {
        $recipe = $saleItem->product->recipe()->with(['items.ingredientProduct', 'items.ingredientPresentation'])->first();

        if (! $recipe) {
            return;
        }

        $yieldQuantity = (string) $recipe->yield_quantity;
        $portions = bccomp($yieldQuantity, '0', 6) === 0 ? $returnedBaseQuantity : bcdiv($returnedBaseQuantity, $yieldQuantity, 6);

        foreach ($recipe->items as $recipeItem) {
            if (! $recipeItem->ingredientProduct->tracks_inventory) {
                continue;
            }

            $baseQuantityPerPortion = $recipeItem->ingredientPresentation
                ? $this->productPresentationConverter->presentationToBase($recipeItem->ingredientPresentation, (string) $recipeItem->quantity)
                : (string) $recipeItem->quantity;

            $incomingQuantity = bcmul($baseQuantityPerPortion, $portions, 6);
            $incomingCost = bcadd((string) ($recipeItem->ingredientProduct->cost ?? '0'), '0', 4);

            $this->returnProductIn($sale, $recipeItem->ingredient_product_id, null, $incomingQuantity, $incomingCost, $saleItem);
        }
    }

    protected function returnProductIn(Sale $sale, int $productId, ?int $productVariantId, string $incomingQuantity, string $incomingUnitCost, SaleItem $referenceItem): void
    {
        $balance = InventoryBalance::query()
            ->where('company_id', $sale->company_id)
            ->where('warehouse_id', $sale->warehouse_id)
            ->where('product_id', $productId)
            ->when(
                $productVariantId,
                fn ($query) => $query->where('product_variant_id', $productVariantId),
                fn ($query) => $query->whereNull('product_variant_id')
            )
            ->lockForUpdate()
            ->first();

        $currentQuantity = $balance?->quantity_on_hand ?? '0.000000';
        $currentAverageCost = $balance?->average_cost ?? '0.0000';
        $newQuantity = bcadd($currentQuantity, $incomingQuantity, 6);
        $newAverageCost = $this->weightedAverage(
            $currentQuantity,
            $currentAverageCost,
            $incomingQuantity,
            $incomingUnitCost,
        );

        InventoryMovement::query()->create([
            'company_id' => $sale->company_id,
            'warehouse_id' => $sale->warehouse_id,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'movement_type' => InventoryMovementType::SaleReturnIn->value,
            'reference_type' => SaleItem::class,
            'reference_id' => $referenceItem->id,
            'quantity_in' => $incomingQuantity,
            'quantity_out' => '0.000000',
            'unit_cost' => $incomingUnitCost,
            'balance_quantity' => $newQuantity,
            'balance_cost' => $newAverageCost,
            'occurred_at' => now(),
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
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'quantity_on_hand' => $newQuantity,
                'average_cost' => $newAverageCost,
            ]);
        }
    }

    protected function resolveStatusAfterReturn(Collection $items): string
    {
        $hasPending = $items->contains(function (SaleItem $item) {
            return bccomp(
                bcsub((string) $item->quantity, (string) $item->returned_quantity, 6),
                '0',
                6
            ) === 1;
        });

        return $hasPending ? SaleStatus::PartiallyReturned->value : SaleStatus::Returned->value;
    }

    protected function toBaseQuantity(SaleItem $saleItem, string $quantity): string
    {
        $basePerUnit = bcdiv((string) $saleItem->base_quantity, (string) $saleItem->quantity, 8);

        return bcmul($quantity, $basePerUnit, 6);
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

    protected function normalizePositiveDecimal(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Cantidad invalida.');
        }

        $normalized = bcadd($value, '0', 6);

        if (bccomp($normalized, '0', 6) <= 0) {
            throw new InvalidArgumentException('La cantidad devuelta debe ser mayor a cero.');
        }

        return $normalized;
    }
}
