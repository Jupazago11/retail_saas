<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTicketViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_ticket_route_renders_sale_and_printing_settings(): void
    {
        [$owner, $company, $sale] = $this->saleTicketFixture();

        app(CompanySettings::class)->set($company, 'general', 'phone', '3001234567');
        app(CompanySettings::class)->set($company, 'general', 'address', 'Calle 123 #45-67');
        app(CompanySettings::class)->set($company, 'general', 'logo_path', 'https://cdn.test/logo-retail.png');
        $company->cashRegisters()->where('is_primary', true)->update(['printer_type' => 'letter_a4']);
        app(CompanySettings::class)->set($company, 'printing', 'show_logo', true);
        app(CompanySettings::class)->set($company, 'printing', 'show_saas_branding', true);

        $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.ticket', $sale))
            ->assertOk()
            ->assertSee('sheet letter_a4', false)
            ->assertSee('https://cdn.test/logo-retail.png', false)
            ->assertSee('Powered by Retail SaaS')
            ->assertSee($company->display_name)
            ->assertSee($sale->document_number)
            ->assertSee('Arroz premium')
            ->assertSee('3600.00');
    }

    public function test_sale_ticket_route_hides_logo_and_branding_when_disabled(): void
    {
        [$owner, $company, $sale] = $this->saleTicketFixture();

        app(CompanySettings::class)->set($company, 'general', 'logo_path', 'https://cdn.test/logo-retail.png');
        $company->cashRegisters()->where('is_primary', true)->update(['printer_type' => 'thermal_80mm']);
        app(CompanySettings::class)->set($company, 'printing', 'show_logo', false);
        app(CompanySettings::class)->set($company, 'printing', 'show_saas_branding', false);

        $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.ticket', $sale))
            ->assertOk()
            ->assertSee('sheet thermal_80mm', false)
            ->assertDontSee('https://cdn.test/logo-retail.png', false)
            ->assertDontSee('Powered by Retail SaaS');
    }

    public function test_sale_ticket_route_is_forbidden_without_sales_view_permission(): void
    {
        [, $company, $sale] = $this->saleTicketFixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'no_sales_ticket',
            'display_name' => 'Sin acceso a ventas',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'products.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'no_sales_ticket',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.ticket', $sale))
            ->assertForbidden();
    }

    protected function saleTicketFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Ticket Retail SAS',
            'display_name' => 'Ticket Retail',
            'tax_id' => '900100200',
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
            'name' => 'Arroz premium',
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

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => now(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        return [$owner, $company, $sale];
    }
}
