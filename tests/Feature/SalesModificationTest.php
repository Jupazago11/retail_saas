<?php

namespace Tests\Feature;

use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\ModifySale;
use App\Actions\Sales\RegisterSalePayments;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SalesModificationTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_it_modifies_confirmed_pos_sale_cancelling_original_and_linking_replacement(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $sale, $cashSession] = $this->posSaleFixture('5', '2');

        $newSale = app(ModifySale::class)->handle($company, $sale, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $owner->id,
            'sale_type' => 'pos',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '3',
                    'unit_price' => '1800',
                ],
            ],
        ], [
            'require_immediate_payment' => true,
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [
                ['payment_method_code' => 'cash', 'amount' => null],
            ],
        ]);

        $this->assertSame(SaleStatus::Confirmed->value, $newSale->status);
        $this->assertSame($sale->id, $newSale->replaces_sale_id);
        $this->assertSame('3.000000', $newSale->items->first()->quantity);

        $original = $sale->fresh(['payments']);
        $this->assertSame(SaleStatus::Cancelled->value, $original->status);
        $this->assertTrue($original->payments->every(fn ($payment) => $payment->status === 'reversed'));

        // Stock: 5 initial - 2 (original, now reversed) - 3 (new) = 3... but original was cancelled
        // so its 2 units were restored before the new sale consumed 3: 5 - 3 = 2 remaining.
        $this->assertSame('2.000000', InventoryBalance::query()->firstOrFail()->quantity_on_hand);
    }

    public function test_it_rolls_back_everything_when_replacement_sale_creation_fails(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $sale, $cashSession] = $this->posSaleFixture('5', '2');

        try {
            app(ModifySale::class)->handle($company, $sale, [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'cash_register_id' => $cashRegister->id,
                'user_id' => $owner->id,
                'sale_type' => 'pos',
                'status' => SaleStatus::Confirmed->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        // Even after the original sale's 2 units are restored (total 5 available),
                        // requesting more than that must fail and roll back the whole operation.
                        'quantity' => '999',
                        'unit_price' => '1800',
                    ],
                ],
            ], [
                'require_immediate_payment' => true,
                'cash_session_id' => $cashSession->id,
                'received_by' => $owner->id,
                'payments' => [
                    ['payment_method_code' => 'cash', 'amount' => null],
                ],
            ]);

            $this->fail('Expected ModifySale to throw due to insufficient stock.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('La venta no tiene stock suficiente para completar la salida.', $exception->getMessage());
        }

        $this->assertSame(SaleStatus::Confirmed->value, $sale->fresh()->status);
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_it_allows_modifying_when_new_quantity_matches_stock_freed_by_cancellation(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $sale, $cashSession] = $this->posSaleFixture('2', '2');

        $this->assertSame('0.000000', InventoryBalance::query()->firstOrFail()->quantity_on_hand);

        $newSale = app(ModifySale::class)->handle($company, $sale, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $owner->id,
            'sale_type' => 'pos',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ], [
            'require_immediate_payment' => true,
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [
                ['payment_method_code' => 'cash', 'amount' => null],
            ],
        ]);

        $this->assertSame(SaleStatus::Confirmed->value, $newSale->status);
        $this->assertSame('0.000000', InventoryBalance::query()->firstOrFail()->quantity_on_hand);
    }

    public function test_it_rejects_modifying_credit_sale(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Modificacion Credito SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
        app(CompanySettings::class)->set($company, 'credit', 'credit_enabled', true);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();
        [$product] = $this->stockedProduct($company, $branch, $warehouse, '10');

        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Cliente',
            'last_name' => 'Credito',
            'credit_enabled' => true,
            'credit_limit' => '100000',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '2', 'unit_price' => '1800'],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Solo se pueden modificar ventas de tipo POS.');

        app(ModifySale::class)->handle($company, $sale, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'sale_type' => 'pos',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '2', 'unit_price' => '1800'],
            ],
        ]);
    }

    public function test_it_rejects_modifying_sale_that_is_no_longer_confirmed(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $sale, $cashSession] = $this->posSaleFixture('5', '2');

        app(ModifySale::class)->handle($company, $sale, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $owner->id,
            'sale_type' => 'pos',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '1800'],
            ],
        ], [
            'require_immediate_payment' => true,
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [['payment_method_code' => 'cash', 'amount' => null]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Esta venta ya fue modificada o anulada por otro proceso.');

        // Simulate a double-submit: try modifying the same (now cancelled) original sale again.
        app(ModifySale::class)->handle($company, $sale, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $owner->id,
            'sale_type' => 'pos',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '1800'],
            ],
        ], [
            'require_immediate_payment' => true,
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [['payment_method_code' => 'cash', 'amount' => null]],
        ]);
    }

    protected function posSaleFixture(string $quantityInStock, string $saleQuantity): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Modificacion Retail SAS',
        ]);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();

        [$product] = $this->stockedProduct($company, $branch, $warehouse, $quantityInStock);

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
            'sale_type' => 'pos',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => $saleQuantity, 'unit_price' => '1800'],
            ],
        ]);

        app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [
                ['payment_method_code' => 'cash', 'amount' => (string) $sale->grand_total],
            ],
        ]);

        return [$owner, $company, $branch, $warehouse, $cashRegister, $product, $sale->fresh(['payments', 'items']), $cashSession];
    }

    protected function stockedProduct(Company $company, Branch $branch, Warehouse $warehouse, string $quantity): array
    {
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
                ['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => '1000'],
            ],
        ]);

        return [$product];
    }
}
