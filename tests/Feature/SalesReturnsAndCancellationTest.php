<?php

namespace Tests\Feature;

use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RegisterSalePayments;
use App\Actions\Sales\ReturnSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\PaymentStatus;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SalesReturnsAndCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_partially_returns_sale_and_restores_inventory(): void
    {
        [$company, $sale, $warehouse, $product] = $this->confirmedSaleFixture(quantityInStock: '5', saleQuantity: '4');

        $sale = app(ReturnSale::class)->handle($company, $sale, [
            [
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '1',
            ],
        ], 'Cliente devolvio una unidad por inconformidad');

        $this->assertSame(SaleStatus::PartiallyReturned->value, $sale->status);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'sale_return_in',
            'quantity_in' => '1.000000',
            'unit_cost' => '1000.0000',
            'balance_quantity' => '2.000000',
            'balance_cost' => '1000.0000',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'id' => $sale->items->first()->id,
            'returned_quantity' => '1.000000',
            'returned_base_quantity' => '1.000000',
        ]);
        $this->assertStringContainsString('[Devolucion] Cliente devolvio una unidad por inconformidad', (string) $sale->fresh()->notes);
        $this->assertSame('2.000000', InventoryBalance::query()->firstOrFail()->quantity_on_hand);
    }

    public function test_it_fully_returns_sale_and_marks_it_as_returned(): void
    {
        [$company, $sale, $warehouse, $product] = $this->confirmedSaleFixture(quantityInStock: '5', saleQuantity: '2');

        $sale = app(ReturnSale::class)->handle($company, $sale, [
            [
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '2',
            ],
        ], 'Producto en mal estado');

        $this->assertSame(SaleStatus::Returned->value, $sale->status);
        $this->assertNotNull($sale->returned_at);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'sale_return_in',
            'quantity_in' => '2.000000',
            'balance_quantity' => '5.000000',
            'balance_cost' => '1000.0000',
        ]);
    }

    public function test_it_rejects_return_when_quantity_exceeds_pending_balance(): void
    {
        [$company, $sale] = $this->confirmedSaleFixture(quantityInStock: '5', saleQuantity: '2');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La cantidad devuelta supera el saldo pendiente de la linea.');

        app(ReturnSale::class)->handle($company, $sale, [
            [
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '3',
            ],
        ], 'Intento invalido');
    }

    public function test_it_cancels_confirmed_sale_restores_inventory_and_reverses_payments(): void
    {
        [$owner, $company, $sale, $cashSession, $warehouse, $product] = $this->confirmedSaleWithPaymentsFixture();

        $sale = app(CancelSale::class)->handle($company, $sale, 'Cliente solicito anulacion inmediata');

        $this->assertSame(SaleStatus::Cancelled->value, $sale->status);
        $this->assertNotNull($sale->cancelled_at);
        $this->assertSame(2, $sale->payments->where('status', PaymentStatus::Reversed->value)->count());
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'sale_return_in',
            'quantity_in' => '2.000000',
            'balance_quantity' => '5.000000',
        ]);
        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'payment_method_code' => 'cash',
            'status' => PaymentStatus::Reversed->value,
        ]);
        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'payment_method_code' => 'card',
            'status' => PaymentStatus::Reversed->value,
        ]);
        $this->assertStringContainsString('[Anulacion] Cliente solicito anulacion inmediata', (string) $sale->fresh()->notes);
        $this->assertSame($cashSession->id, Payment::query()->where('sale_id', $sale->id)->firstOrFail()->cash_session_id);
    }

    public function test_it_rejects_cancelling_sale_after_partial_return(): void
    {
        [$company, $sale] = $this->confirmedSaleFixture(quantityInStock: '5', saleQuantity: '3');

        $sale = app(ReturnSale::class)->handle($company, $sale, [
            [
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '1',
            ],
        ], 'Primera devolucion');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No se puede anular una venta que ya tiene devoluciones registradas.');

        app(CancelSale::class)->handle($company, $sale, 'Intento posterior');
    }

    public function test_it_requires_reason_to_return_sale(): void
    {
        [$company, $sale] = $this->confirmedSaleFixture(quantityInStock: '5', saleQuantity: '2');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Debes indicar un motivo para devolver la venta.');

        app(ReturnSale::class)->handle($company, $sale, [
            [
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '1',
            ],
        ], '');
    }

    public function test_it_requires_reason_to_cancel_sale(): void
    {
        [, $company, $sale] = $this->confirmedSaleWithPaymentsFixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Debes indicar un motivo para anular la venta.');

        app(CancelSale::class)->handle($company, $sale, '');
    }

    protected function confirmedSaleFixture(string $quantityInStock, string $saleQuantity): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Devoluciones Retail SAS',
        ]);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Abarrotes',
            'code' => 'ABA',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz',
            'cost' => 1000,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantityInStock,
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $saleQuantity,
                    'unit_price' => '1800',
                ],
            ],
        ]);

        return [$company, $sale, $warehouse, $product];
    }

    protected function confirmedSaleWithPaymentsFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Anulaciones Retail SAS',
        ]);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Abarrotes',
            'code' => 'ABA',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz',
            'cost' => 1000,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '5',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [
                [
                    'payment_method_code' => 'cash',
                    'amount' => '1600',
                ],
                [
                    'payment_method_code' => 'card',
                    'amount' => '2000',
                ],
            ],
        ]);

        return [$owner, $company, $sale->fresh(['payments', 'items']), $cashSession, $warehouse, $product];
    }
}
