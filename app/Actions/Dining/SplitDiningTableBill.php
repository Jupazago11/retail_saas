<?php

namespace App\Actions\Dining;

use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RegisterSalePayments;
use App\Enums\FrozenSaleStatus;
use App\Enums\SaleStatus;
use App\Models\Company;
use App\Models\DiningTable;
use App\Models\FrozenSale;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cobra la comanda abierta de una mesa, opcionalmente dividida por items
 * entre varios pagadores ("division de cuenta por items" — cada quien paga
 * lo que pidio). Genera UNA Sale confirmada por grupo (cada una con sus
 * propios pagos mixtos ya registrados), todas enlazadas a la misma
 * FrozenSale via `sales.frozen_sale_id` — por eso NO se reusa
 * ConvertFrozenSaleToSale, que asume una relacion 1 a 1 (converted_sale_id).
 *
 * $groups: [
 *   ['label' => ?string, 'item_ids' => int[] (dining_order_item ids), 'payments' => [['payment_method_code','amount','reference'], ...]],
 *   ...
 * ]
 * item_ids de todos los grupos deben cubrir, sin huecos ni duplicados,
 * exactamente las lineas vigentes de la comanda (las canceladas/quitadas ya
 * no estan en payload_snapshot, ver RemoveDiningOrderItem).
 */
class SplitDiningTableBill
{
    public function __construct(
        protected CreateSale $createSale,
        protected RegisterSalePayments $registerSalePayments,
    ) {
    }

    /**
     * @param  array<int, array{label: ?string, item_ids: int[], payments: array}>  $groups
     * @return Sale[]
     */
    public function handle(Company $company, DiningTable $table, array $groups, User $actor): array
    {
        if ($table->company_id !== $company->id) {
            throw new InvalidArgumentException('La mesa no pertenece a la empresa indicada.');
        }

        $groups = array_values(array_filter($groups, fn (array $group) => count($group['item_ids'] ?? []) > 0));

        if ($groups === []) {
            throw new InvalidArgumentException('Asigna al menos un plato a un pagador para poder cobrar.');
        }

        return DB::transaction(function () use ($company, $table, $groups, $actor) {
            $table = DiningTable::query()->lockForUpdate()->findOrFail($table->id);
            $openFrozenSale = $table->openFrozenSale();

            if (! $openFrozenSale) {
                throw new InvalidArgumentException('Esta mesa no tiene una comanda abierta para cobrar.');
            }

            $frozenSale = FrozenSale::query()->lockForUpdate()->findOrFail($openFrozenSale->id);
            $lines = collect($frozenSale->payload_snapshot['items'] ?? []);

            $this->assertGroupsPartitionAllLines($lines, $groups);

            $sales = [];

            foreach ($groups as $group) {
                $groupLines = $lines
                    ->whereIn('dining_order_item_id', $group['item_ids'])
                    ->values()
                    ->all();

                $sale = $this->createSale->handle($company, [
                    'branch_id' => $frozenSale->branch_id,
                    'warehouse_id' => $frozenSale->warehouse_id,
                    'cash_register_id' => $frozenSale->cash_register_id,
                    'customer_id' => $frozenSale->customer_id,
                    'user_id' => $actor->id,
                    'sale_type' => 'pos',
                    'status' => SaleStatus::Confirmed->value,
                    'sold_at' => now(),
                    'items' => $groupLines,
                ]);

                $sale->update([
                    'frozen_sale_id' => $frozenSale->id,
                    'payer_label' => $group['label'] ?? null,
                ]);

                $this->registerSalePayments->handle($company, $sale, [
                    'received_by' => $actor->id,
                    'payments' => $group['payments'] ?? [],
                ]);

                $sales[] = $sale->fresh(['payments']);
            }

            $frozenSale->update([
                'status' => FrozenSaleStatus::Converted->value,
                'converted_sale_id' => count($sales) === 1 ? $sales[0]->id : null,
            ]);

            $table->update(['occupancy_status' => 'free']);

            return $sales;
        });
    }

    protected function assertGroupsPartitionAllLines($lines, array $groups): void
    {
        $expected = $lines->pluck('dining_order_item_id')->filter()->sort()->values()->all();
        $assigned = collect($groups)->flatMap(fn (array $group) => $group['item_ids'])->sort()->values()->all();

        if ($expected !== $assigned) {
            throw new InvalidArgumentException('Cada plato de la comanda debe quedar asignado a un pagador, sin repetirse.');
        }
    }
}
