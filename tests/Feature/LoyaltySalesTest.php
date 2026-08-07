<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Loyalty\ExpireLoyaltyPoints;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\ReturnSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\LoyaltyMovementType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyMovement;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class LoyaltySalesTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_it_awards_points_for_confirmed_sale_when_loyalty_is_enabled(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $customer] = $this->loyaltyFixture();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
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

        $account = $customer->loyaltyAccount()->firstOrFail()->fresh();

        $this->assertSame('3600.0000', $account->points_balance);
        $this->assertDatabaseHas('loyalty_movements', [
            'company_id' => $company->id,
            'loyalty_account_id' => $account->id,
            'sale_id' => $sale->id,
            'movement_type' => LoyaltyMovementType::Earn->value,
            'points' => '3600.0000',
            'cash_equivalent' => '3600.00',
            'balance_after' => '3600.0000',
        ]);
    }

    public function test_it_does_not_award_points_when_company_loyalty_is_disabled(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $customer] = $this->loyaltyFixture(enabled: false);

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->assertDatabaseCount('loyalty_movements', 0);
        $this->assertSame('0.0000', $customer->loyaltyAccount()->firstOrFail()->fresh()->points_balance);
    }

    public function test_it_rejects_loyalty_redemption_when_plan_feature_is_disabled(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Loyalty Basic SAS',
        ]);
        app(CompanySettings::class)->set($company, 'loyalty', 'loyalty_enabled', true);
        app(CompanySettings::class)->set($company, 'loyalty', 'points_rule_type', 'per_currency');
        app(CompanySettings::class)->set($company, 'loyalty', 'points_rate', '1');
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND-BASIC',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Abarrotes',
            'code' => 'ABA-BASIC',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz basic loyalty',
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
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '10',
                'unit_cost' => '1000',
            ]],
        ]);
        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Lucia',
            'last_name' => 'Plan',
            'loyalty_enabled' => true,
        ]);
        $customer->loyaltyAccount()->firstOrFail()->update(['points_balance' => '1000.0000']);
        LoyaltyMovement::query()->create([
            'company_id' => $company->id,
            'loyalty_account_id' => $customer->loyaltyAccount->id,
            'sale_id' => null,
            'movement_type' => LoyaltyMovementType::Earn->value,
            'points' => '1000.0000',
            'cash_equivalent' => '1000.00',
            'balance_after' => '1000.0000',
            'occurred_at' => now()->subDay(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El plan actual no tiene habilitada la feature de fidelizacion.');

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'loyalty_points_to_redeem' => '1000',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '1800',
            ]],
        ]);
    }

    public function test_it_reverses_points_proportionally_on_partial_return(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $customer] = $this->loyaltyFixture();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
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

        app(ReturnSale::class)->handle($company, $sale, [
            [
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '1',
            ],
        ]);

        $account = $customer->loyaltyAccount()->firstOrFail()->fresh();

        $this->assertSame('1800.0000', $account->points_balance);
        $this->assertDatabaseHas('loyalty_movements', [
            'company_id' => $company->id,
            'loyalty_account_id' => $account->id,
            'sale_id' => $sale->id,
            'movement_type' => LoyaltyMovementType::SaleReturnReversal->value,
            'points' => '1800.0000',
            'cash_equivalent' => '1800.00',
            'balance_after' => '1800.0000',
        ]);
    }

    public function test_it_reverses_remaining_points_when_sale_is_cancelled(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $customer] = $this->loyaltyFixture();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
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

        app(CancelSale::class)->handle($company, $sale);

        $account = $customer->loyaltyAccount()->firstOrFail()->fresh();

        $this->assertSame('0.0000', $account->points_balance);
        $this->assertDatabaseHas('loyalty_movements', [
            'company_id' => $company->id,
            'loyalty_account_id' => $account->id,
            'sale_id' => $sale->id,
            'movement_type' => LoyaltyMovementType::SaleCancellationReversal->value,
            'points' => '3600.0000',
            'cash_equivalent' => '3600.00',
            'balance_after' => '0.0000',
        ]);
    }

    public function test_it_rejects_sale_when_customer_loyalty_account_is_missing_but_feature_is_enabled(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $customer] = $this->loyaltyFixture();

        LoyaltyAccount::query()->where('customer_id', $customer->id)->delete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El cliente no tiene una cuenta de fidelizacion creada.');

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
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

    public function test_it_redeems_points_on_confirmed_sale_and_freezes_snapshot(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $customer] = $this->loyaltyFixture();

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
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

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'loyalty_points_to_redeem' => '1000',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $account = $customer->loyaltyAccount()->firstOrFail()->fresh();

        $this->assertSame('1800.00', $sale->subtotal);
        $this->assertSame('1000.00', $sale->discount_total);
        $this->assertSame('800.00', $sale->grand_total);
        $this->assertSame('3400.0000', $account->points_balance);
        $this->assertSame('1000.0000', data_get($sale->pricing_snapshot, 'loyalty_redemption.points'));
        $this->assertSame('1000.00', data_get($sale->pricing_snapshot, 'loyalty_redemption.cash_equivalent'));

        $this->assertDatabaseHas('loyalty_movements', [
            'sale_id' => $sale->id,
            'movement_type' => LoyaltyMovementType::Redeem->value,
            'points' => '1000.0000',
            'cash_equivalent' => '1000.00',
        ]);
        $this->assertDatabaseHas('loyalty_movements', [
            'sale_id' => $sale->id,
            'movement_type' => LoyaltyMovementType::Earn->value,
            'points' => '800.0000',
            'cash_equivalent' => '800.00',
        ]);
    }

    public function test_it_restores_redeemed_points_proportionally_on_partial_return(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $customer] = $this->loyaltyFixture();
        $account = $customer->loyaltyAccount()->firstOrFail();
        $account->update(['points_balance' => '1000.0000']);
        LoyaltyMovement::query()->create([
            'company_id' => $company->id,
            'loyalty_account_id' => $account->id,
            'sale_id' => null,
            'movement_type' => LoyaltyMovementType::Earn->value,
            'points' => '1000.0000',
            'cash_equivalent' => '1000.00',
            'balance_after' => '1000.0000',
            'occurred_at' => now()->subDays(5),
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'loyalty_points_to_redeem' => '1000',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        app(ReturnSale::class)->handle($company, $sale, [
            [
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '1',
            ],
        ]);

        $account = $customer->loyaltyAccount()->firstOrFail()->fresh();

        $this->assertSame('1800.0000', $account->points_balance);
        $this->assertDatabaseHas('loyalty_movements', [
            'sale_id' => $sale->id,
            'movement_type' => LoyaltyMovementType::SaleReturnRedemptionRestore->value,
            'points' => '500.0000',
            'cash_equivalent' => '500.00',
        ]);
        $this->assertDatabaseHas('loyalty_movements', [
            'sale_id' => $sale->id,
            'movement_type' => LoyaltyMovementType::SaleReturnReversal->value,
            'points' => '1300.0000',
            'cash_equivalent' => '1300.00',
        ]);
    }

    public function test_it_restores_redeemed_points_when_sale_is_cancelled(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $customer] = $this->loyaltyFixture();
        $account = $customer->loyaltyAccount()->firstOrFail();
        $account->update(['points_balance' => '1000.0000']);
        LoyaltyMovement::query()->create([
            'company_id' => $company->id,
            'loyalty_account_id' => $account->id,
            'sale_id' => null,
            'movement_type' => LoyaltyMovementType::Earn->value,
            'points' => '1000.0000',
            'cash_equivalent' => '1000.00',
            'balance_after' => '1000.0000',
            'occurred_at' => now()->subDays(5),
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'loyalty_points_to_redeem' => '1000',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        app(CancelSale::class)->handle($company, $sale);

        $account = $customer->loyaltyAccount()->firstOrFail()->fresh();

        $this->assertSame('1000.0000', $account->points_balance);
        $this->assertDatabaseHas('loyalty_movements', [
            'sale_id' => $sale->id,
            'movement_type' => LoyaltyMovementType::SaleCancellationRedemptionRestore->value,
            'points' => '1000.0000',
            'cash_equivalent' => '1000.00',
        ]);
        $this->assertDatabaseHas('loyalty_movements', [
            'sale_id' => $sale->id,
            'movement_type' => LoyaltyMovementType::SaleCancellationReversal->value,
            'points' => '2600.0000',
            'cash_equivalent' => '2600.00',
        ]);
    }

    public function test_it_expires_old_available_points_using_fifo(): void
    {
        [, $company, , , , $customer] = $this->loyaltyFixture();
        $account = $customer->loyaltyAccount()->firstOrFail();

        LoyaltyMovement::query()->create([
            'company_id' => $company->id,
            'loyalty_account_id' => $account->id,
            'sale_id' => null,
            'movement_type' => LoyaltyMovementType::Earn->value,
            'points' => '1000.0000',
            'cash_equivalent' => '1000.00',
            'balance_after' => '1000.0000',
            'occurred_at' => now()->subDays(40),
        ]);
        LoyaltyMovement::query()->create([
            'company_id' => $company->id,
            'loyalty_account_id' => $account->id,
            'sale_id' => null,
            'movement_type' => LoyaltyMovementType::Earn->value,
            'points' => '500.0000',
            'cash_equivalent' => '500.00',
            'balance_after' => '1500.0000',
            'occurred_at' => now()->subDays(5),
        ]);
        $account->update(['points_balance' => '1500.0000']);
        LoyaltyMovement::query()->create([
            'company_id' => $company->id,
            'loyalty_account_id' => $account->id,
            'sale_id' => null,
            'movement_type' => LoyaltyMovementType::Redeem->value,
            'points' => '400.0000',
            'cash_equivalent' => '400.00',
            'balance_after' => '1100.0000',
            'occurred_at' => now()->subDays(2),
        ]);
        $account->update(['points_balance' => '1100.0000']);

        app(CompanySettings::class)->set($company, 'loyalty', 'points_expiration_days', 30);

        $movements = app(ExpireLoyaltyPoints::class)->handle($company, now());

        $account = $customer->loyaltyAccount()->firstOrFail()->fresh();

        $this->assertCount(1, $movements);
        $this->assertSame('600.0000', $movements->first()->points);
        $this->assertSame(LoyaltyMovementType::Expiration->value, $movements->first()->movement_type);
        $this->assertSame('500.0000', $account->points_balance);
    }

    protected function loyaltyFixture(bool $enabled = true): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Fidelizacion Retail SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
        app(CompanySettings::class)->set($company, 'loyalty', 'loyalty_enabled', $enabled);
        app(CompanySettings::class)->set($company, 'loyalty', 'points_rule_type', 'per_currency');
        app(CompanySettings::class)->set($company, 'loyalty', 'points_rate', '1');

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
                    'quantity' => '10',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Lucia',
            'last_name' => 'Torres',
            'loyalty_enabled' => true,
        ]);

        return [$owner, $company, $branch, $warehouse, $product, $customer];
    }
}
