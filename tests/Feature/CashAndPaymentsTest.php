<?php

namespace Tests\Feature;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RegisterSalePayments;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CashAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_opens_cash_session_with_default_opening_amount(): void
    {
        [$owner, $company, $branch, $cashRegister] = $this->cashFixture();
        app(CompanySettings::class)->set($company, 'cash', 'default_opening_amount', '50000');

        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
        ]);

        $this->assertSame('open', $session->status);
        $this->assertSame('50000.00', $session->opening_amount);
        $this->assertNotNull($session->opened_at);
    }

    public function test_it_rejects_second_open_cash_session_for_same_register(): void
    {
        [$owner, $company, $branch, $cashRegister] = $this->cashFixture();

        app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La caja ya tiene una sesion abierta.');

        app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
        ]);
    }

    public function test_company_sequence_is_independent_per_company(): void
    {
        [$ownerA, $companyA, $branchA, $cashRegisterA] = $this->cashFixture();

        // Give company A a head start so its sequence is well ahead of a fresh company.
        $firstSessionA = app(OpenCashSession::class)->handle($companyA, [
            'branch_id' => $branchA->id,
            'cash_register_id' => $cashRegisterA->id,
            'opened_by' => $ownerA->id,
        ]);
        app(CloseCashSession::class)->handle($companyA, $firstSessionA, [
            'closed_by' => $ownerA->id,
            'closing_counted_amount' => '0',
        ]);
        $secondSessionA = app(OpenCashSession::class)->handle($companyA, [
            'branch_id' => $branchA->id,
            'cash_register_id' => $cashRegisterA->id,
            'opened_by' => $ownerA->id,
        ]);

        $this->assertSame(1, $firstSessionA->company_sequence);
        $this->assertSame(2, $secondSessionA->company_sequence);

        [$ownerB, $companyB, $branchB, $cashRegisterB] = $this->cashFixture();
        $firstSessionB = app(OpenCashSession::class)->handle($companyB, [
            'branch_id' => $branchB->id,
            'cash_register_id' => $cashRegisterB->id,
            'opened_by' => $ownerB->id,
        ]);

        // Company B's first session starts at 1 regardless of company A's global row count.
        $this->assertSame(1, $firstSessionB->company_sequence);
        $this->assertGreaterThan($secondSessionA->id, $firstSessionB->id);
    }

    public function test_it_opens_cash_session_with_zero_amount_when_opening_is_not_required(): void
    {
        [$owner, $company, $branch, $cashRegister] = $this->cashFixture();
        app(CompanySettings::class)->set($company, 'cash', 'opening_required', false);
        app(CompanySettings::class)->set($company, 'cash', 'default_opening_amount', '50000');

        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
        ]);

        $this->assertSame('0.00', $session->opening_amount);
        $this->assertSame('0.00', $session->closing_expected_amount);
    }

    public function test_it_registers_mixed_payments_for_confirmed_sale_and_links_open_cash_session(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->saleAndCashFixture();

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

        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '100000',
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

        $payments = app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $session->id,
            'received_by' => $owner->id,
            'payments' => [
                [
                    'payment_method_code' => 'cash',
                    'amount' => '1600',
                ],
                [
                    'payment_method_code' => 'card',
                    'amount' => '2000',
                    'reference' => 'TX-100',
                ],
            ],
        ]);

        $this->assertCount(2, $payments);
        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'sale_id' => $sale->id,
            'cash_session_id' => $session->id,
            'payment_method_code' => 'cash',
            'amount' => '1600.00',
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'sale_id' => $sale->id,
            'cash_session_id' => $session->id,
            'payment_method_code' => 'card',
            'amount' => '2000.00',
            'reference' => 'TX-100',
        ]);
    }

    public function test_it_rejects_payment_registration_when_total_does_not_match_sale(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->saleAndCashFixture();

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

        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
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
        $this->expectExceptionMessage('La suma de los pagos debe coincidir exactamente con el total de la venta.');

        app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $session->id,
            'received_by' => $owner->id,
            'payments' => [
                [
                    'payment_method_code' => 'cash',
                    'amount' => '1700',
                ],
            ],
        ]);
    }

    public function test_it_closes_cash_session_with_difference_and_marks_it_closed(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->saleAndCashFixture();
        app(CompanySettings::class)->set($company, 'cash', 'allow_close_with_difference', true);

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

        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '100000',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $session->id,
            'received_by' => $owner->id,
            'payments' => [
                [
                    'payment_method_code' => 'cash',
                    'amount' => '1800',
                ],
            ],
        ]);

        $closedSession = app(CloseCashSession::class)->handle($company, $session, [
            'closed_by' => $owner->id,
            'closing_counted_amount' => '101700',
        ]);

        $this->assertSame('closed', $closedSession->status);
        $this->assertSame('101800.00', $closedSession->closing_expected_amount);
        $this->assertSame('101700.00', $closedSession->closing_counted_amount);
        $this->assertSame('-100.00', $closedSession->difference_amount);
    }

    public function test_it_marks_cash_session_as_reconciled_when_difference_is_zero(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->saleAndCashFixture();

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

        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '100000',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $session->id,
            'received_by' => $owner->id,
            'payments' => [
                [
                    'payment_method_code' => 'cash',
                    'amount' => '1800',
                ],
            ],
        ]);

        $reconciled = app(CloseCashSession::class)->handle($company, $session, [
            'closed_by' => $owner->id,
            'closing_counted_amount' => '101800',
        ]);

        $this->assertSame('reconciled', $reconciled->status);
        $this->assertSame('0.00', $reconciled->difference_amount);
    }

    public function test_it_rejects_closing_cash_session_with_difference_when_company_disallows_it(): void
    {
        [$owner, $company, $branch, $cashRegister] = $this->cashFixture();

        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La empresa no permite cerrar caja con diferencia.');

        app(CloseCashSession::class)->handle($company, $session, [
            'closed_by' => $owner->id,
            'closing_counted_amount' => '49900',
        ]);
    }

    protected function cashFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Caja Retail SAS',
        ]);

        return [$owner, $company, $company->branches()->firstOrFail(), $company->cashRegisters()->firstOrFail()];
    }

    protected function saleAndCashFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Pagos Retail SAS',
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
            'cost' => 900,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$owner, $company, $branch, $warehouse, $cashRegister, $product];
    }
}
