<?php

namespace App\Actions\Dining;

use App\Enums\FrozenSaleStatus;
use App\Models\DiningOrderItem;
use App\Models\FrozenSale;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Sales\SaleCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * El mesero/cajero cambia la cantidad de un plato ya agregado a una comanda
 * abierta. Recalcula esa linea en payload_snapshot (la fuente real de lo que
 * se convertira en Sale al cobrar) y marca el plato como "modificado" para
 * que cocina lo note.
 */
class UpdateDiningOrderItemQuantity
{
    public function __construct(
        protected SaleCalculator $saleCalculator,
    ) {
    }

    public function handle(DiningOrderItem $item, string $newQuantity, User $actor): DiningOrderItem
    {
        return DB::transaction(function () use ($item, $newQuantity, $actor) {
            $item = DiningOrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $frozenSale = FrozenSale::query()->lockForUpdate()->findOrFail($item->frozen_sale_id);

            if ($frozenSale->status !== FrozenSaleStatus::Open->value) {
                throw new InvalidArgumentException('Solo se pueden editar comandas abiertas.');
            }

            $payload = $frozenSale->payload_snapshot;
            $lines = collect($payload['items'] ?? []);
            $lineIndex = $lines->search(fn (array $line) => ($line['dining_order_item_id'] ?? null) === $item->id);

            if ($lineIndex === false) {
                throw new InvalidArgumentException('No se encontro este plato en la comanda.');
            }

            $line = $lines[$lineIndex];
            $product = Product::query()->findOrFail($line['product_id']);
            $presentation = $line['product_presentation_id']
                ? ProductPresentation::query()->find($line['product_presentation_id'])
                : null;
            $variant = $line['product_variant_id']
                ? ProductVariant::query()->find($line['product_variant_id'])
                : null;

            $recalculated = $this->saleCalculator->calculateLine(
                $product,
                $presentation,
                $variant,
                $newQuantity,
                $line['unit_price'],
                $line['discount_amount'],
                $line['tax_rate'],
            );
            $recalculated['dining_order_item_id'] = $item->id;

            $lines[$lineIndex] = $recalculated;
            $payload['items'] = $lines->values()->all();
            $payload['totals'] = $this->saleCalculator->calculateTotals($payload['items']);

            $frozenSale->update(['payload_snapshot' => $payload]);

            $item->update([
                'quantity' => $newQuantity,
                'is_modified' => true,
                'modified_by' => $actor->id,
            ]);

            return $item->fresh();
        });
    }
}
