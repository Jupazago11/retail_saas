<?php

namespace App\Actions\Dining;

use App\Actions\Sales\CreateFrozenSale;
use App\Actions\Sales\UpdateFrozenSaleItems;
use App\Enums\RecordStatus;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\DiningOrderItem;
use App\Models\DiningTable;
use App\Models\FrozenSale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Agrega un plato a la comanda de una mesa. Si la mesa no tiene una comanda
 * abierta todavia, crea la FrozenSale que la representa (primer plato de la
 * mesa); si ya existe, solo le agrega la linea nueva.
 */
class AddDishToDiningOrder
{
    public function __construct(
        protected CreateFrozenSale $createFrozenSale,
        protected UpdateFrozenSaleItems $updateFrozenSaleItems,
    ) {
    }

    public function handle(Company $company, DiningTable $table, array $item, User $user): FrozenSale
    {
        if ($table->company_id !== $company->id) {
            throw new InvalidArgumentException('La mesa no pertenece a la empresa indicada.');
        }

        return DB::transaction(function () use ($company, $table, $item, $user) {
            $table = DiningTable::query()->lockForUpdate()->findOrFail($table->id);
            $openFrozenSale = $table->openFrozenSale();

            if ($openFrozenSale) {
                $frozenSale = $this->updateFrozenSaleItems->handle($company, $openFrozenSale, $item);
            } else {
                $warehouse = $this->resolvePrimaryWarehouse($company, $table->branch_id);
                $cashRegister = $this->resolvePrimaryCashRegister($company, $table->branch_id);

                $frozenSale = $this->createFrozenSale->handle($company, [
                    'branch_id' => $table->branch_id,
                    'warehouse_id' => $warehouse->id,
                    'cash_register_id' => $cashRegister?->id,
                    'created_by' => $user->id,
                    'label' => "Mesa {$table->name}",
                    'items' => [$item],
                ]);

                $frozenSale->update(['dining_table_id' => $table->id]);
            }

            $notes = trim((string) ($item['notes'] ?? ''));

            $diningOrderItem = DiningOrderItem::query()->create([
                'frozen_sale_id' => $frozenSale->id,
                'product_id' => (int) $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'quantity' => (string) ($item['quantity'] ?? 1),
                'notes' => $notes !== '' ? $notes : null,
                'kitchen_status' => 'pending',
                'created_by' => $user->id,
            ]);

            // CreateFrozenSale/UpdateFrozenSaleItems siempre agregan la
            // linea nueva al final de payload_snapshot['items'] — se marca
            // esa ultima linea con el id del DiningOrderItem recien creado
            // para poder encontrarla despues al editar/quitar el plato.
            $payload = $frozenSale->payload_snapshot;
            $lastIndex = array_key_last($payload['items']);
            $payload['items'][$lastIndex]['dining_order_item_id'] = $diningOrderItem->id;
            $frozenSale->update(['payload_snapshot' => $payload]);

            $table->update(['occupancy_status' => 'occupied']);

            return $frozenSale->fresh(['diningTable', 'diningOrderItems.product']);
        });
    }

    protected function resolvePrimaryWarehouse(Company $company, int $branchId): Warehouse
    {
        return Warehouse::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $branchId)
            ->where('status', RecordStatus::Active->value)
            ->orderByDesc('is_primary')
            ->firstOrFail();
    }

    protected function resolvePrimaryCashRegister(Company $company, int $branchId): ?CashRegister
    {
        return CashRegister::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $branchId)
            ->where('status', RecordStatus::Active->value)
            ->orderByDesc('is_primary')
            ->first();
    }
}
