<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\LoyaltyMovementType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Livewire\Loyalty\LoyaltyAccountsPage;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\LoyaltyMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class LoyaltyAccountsPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_loyalty_page_lists_accounts_and_expires_points(): void
    {
        [$owner, $company, $customer] = $this->loyaltyUiFixture();
        $movement = LoyaltyMovement::query()
            ->where('company_id', $company->id)
            ->where('movement_type', LoyaltyMovementType::Earn->value)
            ->firstOrFail();
        $movement->update([
            'occurred_at' => now()->subDays(45),
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(LoyaltyAccountsPage::class)
            ->assertSee('Cuentas de puntos')
            ->assertSee($customer->person->first_name)
            ->set('expirationAsOf', now()->format('Y-m-d'))
            ->call('expirePoints')
            ->assertHasNoErrors()
            ->assertSee('Expiracion');

        $this->assertSame('0.0000', $customer->loyaltyAccount()->firstOrFail()->fresh()->points_balance);
        $this->assertDatabaseHas('loyalty_movements', [
            'company_id' => $company->id,
            'loyalty_account_id' => $customer->loyaltyAccount->id,
            'movement_type' => LoyaltyMovementType::Expiration->value,
            'points' => '3600.0000',
            'balance_after' => '0.0000',
        ]);
    }

    public function test_loyalty_page_can_apply_manual_credit_adjustment(): void
    {
        [$owner, $company, $customer] = $this->loyaltyUiFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(LoyaltyAccountsPage::class)
            ->call('startAdjustingAccount', $customer->loyaltyAccount->id)
            ->set('adjustmentType', 'credit')
            ->set('adjustmentReasonCode', 'promotion_compensation')
            ->set('adjustmentPoints', '500')
            ->set('adjustmentNotes', 'Ajuste comercial')
            ->call('applyAdjustment')
            ->assertHasNoErrors()
            ->assertSee('Ajuste manual a favor');

        $this->assertSame('4100.0000', $customer->loyaltyAccount()->firstOrFail()->fresh()->points_balance);
        $this->assertDatabaseHas('loyalty_movements', [
            'company_id' => $company->id,
            'loyalty_account_id' => $customer->loyaltyAccount->id,
            'movement_type' => LoyaltyMovementType::ManualCredit->value,
            'points' => '500.0000',
            'cash_equivalent' => '500.00',
            'balance_after' => '4100.0000',
            'notes' => '[promotion_compensation] Ajuste comercial',
        ]);
    }

    public function test_loyalty_page_route_is_forbidden_without_manage_permission(): void
    {
        [, $company] = $this->loyaltyUiFixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'loyalty_view_only',
            'display_name' => 'Sin loyalty manage',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'sales.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'loyalty_view_only',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('loyalty.index'))
            ->assertForbidden();
    }

    protected function loyaltyUiFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Loyalty UI SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
        app(CompanySettings::class)->set($company, 'loyalty', 'loyalty_enabled', true);
        app(CompanySettings::class)->set($company, 'loyalty', 'points_rate', '1');
        app(CompanySettings::class)->set($company, 'loyalty', 'points_expiration_days', 30);

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
            'name' => 'Arroz loyalty UI',
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
            'first_name' => 'Laura',
            'last_name' => 'Puntos',
            'document_number' => '9001',
            'loyalty_enabled' => true,
        ]);

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

        return [$owner, $company, $customer->fresh(['person', 'loyaltyAccount'])];
    }
}
