<?php

namespace Tests\Feature;

use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Credit\RegisterCreditPayment;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RegisterSalePayments;
use App\Actions\Sales\ReturnSale;
use App\Enums\CreditMovementType;
use App\Enums\InventoryAdjustmentType;
use App\Enums\PaymentStatus;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\CreditAccount;
use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreditSalesAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_confirmed_credit_sale_and_registers_charge_on_customer_account(): void
    {
        [$owner, $company, $branch, $warehouse, , $product, $customer] = $this->creditFixture();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $account = $customer->creditAccount()->firstOrFail()->fresh();

        $this->assertSame('credit', $sale->sale_type);
        $this->assertSame($account->id, $sale->credit_account_id);
        $this->assertSame('3600.00', $account->balance_due);
        $this->assertSame('6400.00', $account->available_credit);
        $this->assertDatabaseHas('credit_movements', [
            'company_id' => $company->id,
            'credit_account_id' => $account->id,
            'sale_id' => $sale->id,
            'movement_type' => CreditMovementType::SaleCharge->value,
            'amount' => '3600.00',
            'balance_after' => '3600.00',
        ]);
        $this->assertNotNull($sale->credit_due_at);
    }

    public function test_it_rejects_credit_sale_without_customer_when_required(): void
    {
        [$owner, $company, $branch, $warehouse, , $product] = $this->creditFixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La venta a credito requiere un cliente.');

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);
    }

    public function test_it_blocks_new_credit_sale_when_customer_has_overdue_balance_and_setting_is_enabled(): void
    {
        [$owner, $company, $branch, $warehouse, , $product, $customer] = $this->creditFixture();

        $overdueSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $overdueSale->update([
            'credit_due_at' => now()->subDay(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El cliente tiene cartera vencida y la empresa bloquea nuevos creditos.');

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);
    }

    public function test_it_allows_new_credit_sale_with_overdue_balance_when_setting_is_disabled(): void
    {
        [$owner, $company, $branch, $warehouse, , $product, $customer] = $this->creditFixture();
        app(CompanySettings::class)->set($company, 'credit', 'block_new_credit_if_overdue', false);

        $overdueSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $overdueSale->update([
            'credit_due_at' => now()->subDay(),
        ]);

        $newSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->assertSame('credit', $newSale->sale_type);
        $this->assertNotNull($newSale->credit_account_id);
    }

    public function test_it_registers_credit_payment_and_reduces_sale_and_account_balance(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $customer] = $this->creditFixture();

        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $payment = app(RegisterCreditPayment::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payment_method_code' => 'cash',
            'amount' => '1000',
            'reference' => 'AB-001',
        ]);

        $account = $customer->creditAccount()->firstOrFail()->fresh();

        $this->assertSame($sale->id, $payment->sale_id);
        $this->assertSame($account->id, $payment->credit_account_id);
        $this->assertSame('2600.00', $account->balance_due);
        $this->assertSame('7400.00', $account->available_credit);
        $this->assertDatabaseHas('credit_movements', [
            'company_id' => $company->id,
            'credit_account_id' => $account->id,
            'sale_id' => $sale->id,
            'movement_type' => CreditMovementType::Payment->value,
            'amount' => '1000.00',
            'balance_after' => '2600.00',
        ]);
    }

    public function test_it_rejects_credit_payment_with_cash_session_from_other_register(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $customer] = $this->creditFixture();

        $foreignRegister = $company->cashRegisters()->create([
            'branch_id' => $branch->id,
            'name' => 'Caja alterna',
            'code' => 'CAJA-ALT',
            'status' => RecordStatus::Active->value,
        ]);

        $foreignSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $foreignRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '20000',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La sesion de caja debe corresponder a la misma caja operativa de la venta.');

        app(RegisterCreditPayment::class)->handle($company, $sale, [
            'cash_session_id' => $foreignSession->id,
            'received_by' => $owner->id,
            'payment_method_code' => 'cash',
            'amount' => '1000',
        ]);
    }

    public function test_it_rejects_immediate_sale_payments_for_credit_sale(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $customer] = $this->creditFixture();

        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Las ventas a credito registran abonos, no pagos inmediatos de venta.');

        app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [
                [
                    'payment_method_code' => 'cash',
                    'amount' => '1800',
                ],
            ],
        ]);
    }

    public function test_it_reduces_credit_balance_when_credit_sale_is_partially_returned_without_abonos(): void
    {
        [$owner, $company, $branch, $warehouse, , $product, $customer] = $this->creditFixture();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $sale = app(ReturnSale::class)->handle($company, $sale, [
            [
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '1',
            ],
        ], 'Cliente devolvio una unidad');

        $account = $customer->creditAccount()->firstOrFail()->fresh();

        $this->assertSame(SaleStatus::PartiallyReturned->value, $sale->status);
        $this->assertSame('1800.00', $account->balance_due);
        $this->assertDatabaseHas('credit_movements', [
            'company_id' => $company->id,
            'credit_account_id' => $account->id,
            'sale_id' => $sale->id,
            'movement_type' => CreditMovementType::SaleReturnAdjustment->value,
            'amount' => '1800.00',
            'balance_after' => '1800.00',
        ]);
    }

    public function test_it_rejects_cancelling_credit_sale_after_abono(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $customer] = $this->creditFixture();

        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        app(RegisterCreditPayment::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payment_method_code' => 'cash',
            'amount' => '500',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No se puede anular una venta a credito que ya tiene abonos registrados.');

        app(CancelSale::class)->handle($company, $sale, 'Intento de anulacion posterior a abono');
    }

    protected function creditFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Credito Retail SAS',
        ]);
        app(CompanySettings::class)->set($company, 'credit', 'credit_enabled', true);
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
                    'quantity' => '10',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Maria',
            'last_name' => 'Perez',
            'credit_enabled' => true,
            'credit_limit' => '10000',
        ]);

        return [$owner, $company, $branch, $warehouse, $cashRegister, $product, $customer];
    }
}
