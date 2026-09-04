<?php

namespace App\Actions\Dining;

use App\Enums\FrozenSaleStatus;
use App\Models\DiningOrderItem;
use App\Models\FrozenSale;
use App\Models\User;
use App\Services\Sales\SaleCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Quita un plato de una comanda abierta. Si cocina todavia no lo habia
 * tocado (pending), se borra sin dejar rastro. Si ya estaba en curso
 * (preparing/on_hold/ready), se marca "cancelled" en vez de desaparecer, para
 * que cocina note que se cancelo algo que ya estaba preparando.
 */
class RemoveDiningOrderItem
{
    public function __construct(
        protected SaleCalculator $saleCalculator,
    ) {
    }

    public function handle(DiningOrderItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor) {
            $item = DiningOrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $frozenSale = FrozenSale::query()->lockForUpdate()->findOrFail($item->frozen_sale_id);

            if ($frozenSale->status !== FrozenSaleStatus::Open->value) {
                throw new InvalidArgumentException('Solo se pueden editar comandas abiertas.');
            }

            $payload = $frozenSale->payload_snapshot;
            $remainingLines = collect($payload['items'] ?? [])
                ->reject(fn (array $line) => ($line['dining_order_item_id'] ?? null) === $item->id)
                ->values();

            if ($remainingLines->isEmpty()) {
                throw new InvalidArgumentException('La comanda debe tener al menos un plato — cancela la mesa completa si quieres vaciarla.');
            }

            $payload['items'] = $remainingLines->all();
            $payload['totals'] = $this->saleCalculator->calculateTotals($payload['items']);
            $frozenSale->update(['payload_snapshot' => $payload]);

            if ($item->kitchen_status === 'pending') {
                $item->delete();

                return;
            }

            $item->update([
                'kitchen_status' => 'cancelled',
                'is_modified' => true,
                'modified_by' => $actor->id,
            ]);
        });
    }
}
