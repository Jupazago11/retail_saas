<?php

namespace App\Actions\Inventory;

use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class BulkSetupProductInventory
{
    public function __construct(
        protected CreateInventoryAdjustment $createInventoryAdjustment,
        protected AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<int, array{product_id: int, tracks_inventory: bool, minimum_stock: numeric-string|float|int, quantities: array<int, numeric-string|float|int>}>  $rows
     */
    public function handle(Company $company, array $rows): void
    {
        DB::transaction(function () use ($company, $rows) {
            $itemsByWarehouse = [];

            foreach ($rows as $row) {
                $product = Product::query()
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->findOrFail((int) $row['product_id']);

                $product->update([
                    'tracks_inventory' => (bool) $row['tracks_inventory'],
                    'minimum_stock' => (string) ($row['minimum_stock'] ?? 0),
                ]);

                foreach ($row['quantities'] ?? [] as $warehouseId => $quantity) {
                    if ((float) $quantity <= 0) {
                        continue;
                    }

                    $itemsByWarehouse[(int) $warehouseId][] = [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_cost' => (string) $product->cost,
                    ];
                }
            }

            foreach ($itemsByWarehouse as $warehouseId => $items) {
                $warehouse = Warehouse::query()
                    ->where('company_id', $company->id)
                    ->findOrFail($warehouseId);

                $this->createInventoryAdjustment->handle($company, [
                    'branch_id' => $warehouse->branch_id,
                    'warehouse_id' => $warehouse->id,
                    'adjustment_type' => 'increase',
                    'reason' => 'Carga inicial de inventario',
                    'items' => $items,
                ]);
            }

            $this->auditLogger->logSnapshot(
                $company,
                'inventory.setup_wizard.bulk_loaded',
                Company::class,
                $company->id,
                null,
                ['products_configured' => count($rows), 'warehouses_loaded' => array_keys($itemsByWarehouse)],
            );
        });
    }
}
