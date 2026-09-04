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
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SaleTicketViewTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_sale_ticket_route_renders_sale_and_printing_settings(): void
    {
        [$owner, $company, $sale] = $this->saleTicketFixture();

        app(CompanySettings::class)->set($company, 'general', 'phone', '3001234567');
        app(CompanySettings::class)->set($company, 'general', 'address', 'Calle 123 #45-67');
        $company->cashRegisters()->where('is_primary', true)->update(['printer_type' => 'letter_a4']);
        app(CompanySettings::class)->set($company, 'printing', 'show_logo', true);

        // La URL del logo ya no se muestra directo desde el setting: se
        // resuelve via UpdateCompanyLogo::currentPrintUrl(), que exige R2
        // configurado (Storage::disk('r2')->temporaryUrl(...)) y sin eso
        // siempre retorna null — no hay credenciales reales en test/dev,
        // asi que no se puede afirmar la URL aqui.
        $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.ticket', $sale))
            ->assertOk()
            ->assertSee('sheet letter_a4', false)
            ->assertSee('Desarrollado por Retail SaaS')
            ->assertSee($company->display_name)
            ->assertSee($sale->document_number)
            ->assertSee('Arroz premium')
            // Money::format(): separador de miles con punto, sin decimales.
            ->assertSee('3.600');
    }

    public function test_sale_ticket_route_hides_logo_when_disabled(): void
    {
        [$owner, $company, $sale] = $this->saleTicketFixture();

        $company->cashRegisters()->where('is_primary', true)->update(['printer_type' => 'thermal_80mm']);
        app(CompanySettings::class)->set($company, 'printing', 'show_logo', false);

        $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.ticket', $sale))
            ->assertOk()
            ->assertSee('sheet thermal_80mm', false)
            // La marca del SaaS ya no es configurable por empresa (ver
            // resources/views/printing/sales/ticket.blade.php): siempre va.
            ->assertSee('Desarrollado por Retail SaaS');
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
        $this->assignCompanyPlan($company, 'basic');
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
