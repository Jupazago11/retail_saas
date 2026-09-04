<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\SaleStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Products\ProductPresentationConverter;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostSaleToInventory
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected ProductPresentationConverter $productPresentationConverter,
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
        if ($item->product->is_recipe) {
            $this->postRecipeItem($sale, $item);
            return;
        }

        if (! $item->product->tracks_inventory) {
            return;
        }

        $movementUnitCost = $this->postProductOut(
            $sale,
            $item->product_id,
            $item->product_variant_id,
            (string) $item->base_quantity,
            $this->resolveMovementUnitCost($item),
            $item
        );

        $item->update([
            'cost_snapshot' => $movementUnitCost,
        ]);
    }

    /**
     * Un plato con receta no tiene stock propio: en vez de descontar el
     * producto vendido, descuenta cada insumo de su receta (cantidad de la
     * receta convertida a la unidad base del insumo, multiplicada por lo
     * vendido y ajustada por el rendimiento de la receta).
     */
    protected function postRecipeItem(Sale $sale, SaleItem $item): void
    {
        $recipe = $item->product->recipe()->with(['items.ingredientProduct', 'items.ingredientPresentation'])->first();

        if (! $recipe) {
            return;
        }

        $soldQuantity = (string) $item->base_quantity;
        $yieldQuantity = (string) $recipe->yield_quantity;
        $portions = bccomp($yieldQuantity, '0', 6) === 0 ? $soldQuantity : bcdiv($soldQuantity, $yieldQuantity, 6);

        $realizedCost = '0';

        foreach ($recipe->items as $recipeItem) {
            if (! $recipeItem->ingredientProduct->tracks_inventory) {
                continue;
            }

            $baseQuantityPerPortion = $recipeItem->ingredientPresentation
                ? $this->productPresentationConverter->presentationToBase($recipeItem->ingredientPresentation, (string) $recipeItem->quantity)
                : (string) $recipeItem->quantity;

            $outgoingQuantity = bcmul($baseQuantityPerPortion, $portions, 6);
            $ingredientFallbackCost = bcadd((string) ($recipeItem->ingredientProduct->cost ?? '0'), '0', 4);

            $unitCost = $this->postProductOut($sale, $recipeItem->ingredient_product_id, null, $outgoingQuantity, $ingredientFallbackCost, $item);

            $realizedCost = bcadd($realizedCost, bcmul($outgoingQuantity, $unitCost, 6), 6);
        }

        $item->update([
            'cost_snapshot' => bccomp($soldQuantity, '0', 6) === 0 ? '0' : bcdiv($realizedCost, $soldQuantity, 4),
        ]);
    }

    /**
     * Descuenta $outgoingQuantity de un producto/variante en la bodega de la
     * venta, actualiza su InventoryBalance y registra el InventoryMovement.
     * Retorna el costo unitario usado en el movimiento. $fallbackCost se usa
     * solo cuando el balance todavia no tiene costo promedio (null = usar el
     * costo del producto, igual que resolveMovementUnitCost).
     */
    protected function postProductOut(Sale $sale, int $productId, ?int $productVariantId, string $outgoingQuantity, ?string $fallbackCost, SaleItem $referenceItem): string
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
        $allowNegativeStock = (bool) $this->companySettings->get($sale->company_id, 'pos', 'allow_negative_stock');

        if (! $allowNegativeStock && bccomp($currentQuantity, $outgoingQuantity, 6) < 0) {
            throw new InvalidArgumentException('La venta no tiene stock suficiente para completar la salida.');
        }

        $newQuantity = bcsub($currentQuantity, $outgoingQuantity, 6);
        $movementUnitCost = bccomp($currentAverageCost, '0', 4) === 1
            ? $currentAverageCost
            : bcadd($fallbackCost ?? '0', '0', 4);
        $newAverageCost = bccomp($newQuantity, '0', 6) === 0
            ? '0.0000'
            : $movementUnitCost;

        InventoryMovement::query()->create([
            'company_id' => $sale->company_id,
            'warehouse_id' => $sale->warehouse_id,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'movement_type' => InventoryMovementType::SaleOut->value,
            'reference_type' => SaleItem::class,
            'reference_id' => $referenceItem->id,
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
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'quantity_on_hand' => $newQuantity,
                'average_cost' => $newAverageCost,
            ]);
        }

        return $movementUnitCost;
    }

    protected function shouldPost(Sale $sale): bool
    {
        return $sale->status === SaleStatus::Confirmed->value;
    }

    protected function resolveMovementUnitCost(SaleItem $item): string
    {
        return bcadd((string) ($item->product->cost ?? '0'), '0', 4);
    }
}
