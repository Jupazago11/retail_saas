<?php

namespace App\Services\Inventory;

use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Services\Products\ProductPresentationConverter;

class RecipeCostCalculator
{
    public function __construct(
        protected ProductPresentationConverter $converter,
    ) {
    }

    /**
     * Costo del plato = suma del costo de cada insumo (convertido a su
     * unidad base) dividido entre las porciones que rinde la receta. Escribe
     * el resultado en products.cost del plato y lo retorna.
     */
    public function recalculate(Recipe $recipe): string
    {
        $recipe->loadMissing(['items.ingredientProduct', 'items.ingredientPresentation', 'product']);

        $totalCost = $recipe->items->reduce(function (string $carry, RecipeItem $item) {
            $baseQuantity = $item->ingredientPresentation
                ? $this->converter->presentationToBase($item->ingredientPresentation, (string) $item->quantity)
                : (string) $item->quantity;

            $ingredientCost = (string) ($item->ingredientProduct->cost ?? '0');

            return bcadd($carry, bcmul($baseQuantity, $ingredientCost, 6), 6);
        }, '0');

        $yieldQuantity = (string) $recipe->yield_quantity;
        $costPerUnit = bccomp($yieldQuantity, '0', 6) === 0
            ? $totalCost
            : bcdiv($totalCost, $yieldQuantity, 4);

        $recipe->product->update(['cost' => $costPerUnit]);

        return $costPerUnit;
    }
}
