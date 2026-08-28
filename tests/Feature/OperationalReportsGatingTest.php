<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Company\CreateCashRegister;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Livewire\Reports\OperationalReportsPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\OperationalReportService;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class OperationalReportsGatingTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_basic_plan_hides_credit_loyalty_promotions_and_profitability_sections(): void
    {
        [$owner, $company] = $this->fixture('basic');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(OperationalReportsPage::class)
            ->assertDontSee('Cartera')
            ->assertDontSee('Puntos vigentes')
            ->assertDontSee('Promociones')
            ->assertDontSee('Aging de cartera')
            ->assertDontSee('Actividad comercial reciente')
            ->assertDontSee('Margen bruto')
            ->assertSee('Ventas por dia')
            ->assertSee('Top vendidos');
    }

    public function test_premium_plan_shows_credit_loyalty_promotions_and_profitability_sections(): void
    {
        [$owner, $company] = $this->fixture('premium');

        // El plan solo habilita el modulo; la empresa tambien tiene que
        // prenderlo operativamente (ver OperationalReportsPage::creditEnabled()).
        app(CompanySettings::class)->set($company, 'credit', 'credit_enabled', true);
        app(CompanySettings::class)->set($company, 'loyalty', 'loyalty_enabled', true);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(OperationalReportsPage::class)
            ->assertSee('Cartera')
            ->assertSee('Puntos vigentes')
            ->assertSee('Promociones')
            ->assertSee('Aging de cartera')
            ->assertSee('Actividad comercial reciente')
            ->assertSee('Margen bruto');
    }

    public function test_premium_plan_hides_cartera_when_company_has_not_turned_credit_on(): void
    {
        [$owner, $company] = $this->fixture('premium');

        // El plan premium incluye el modulo de credito, pero la empresa
        // nunca lo activo operativamente (Configuracion > Credito) — el
        // reporte no deberia mostrar "Cartera" solo porque el plan lo permite.
        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(OperationalReportsPage::class)
            ->assertDontSee('Cartera')
            ->assertDontSee('Aging de cartera')
            ->assertDontSee('Puntos vigentes');
    }

    public function test_basic_plan_hides_the_purchases_versus_sales_contrast(): void
    {
        // Plan Basico no incluye 'purchases' en PlanCatalog: la columna
        // Compras y el contraste Ingresos/Gastos no deberian aparecer, solo
        // el panel de Ventas (a ancho completo).
        [$owner, $company] = $this->fixture('basic');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(OperationalReportsPage::class)
            ->assertDontSee('Gastos del periodo')
            ->assertDontSee('Lo que le compraste a tus proveedores')
            ->assertDontSee('Mayor gasto')
            ->assertSee('Lo que tus clientes te compraron')
            ->assertSee('Ventas por dia');
    }

    public function test_sales_trend_groups_totals_by_day_within_the_filtered_range(): void
    {
        [, $company, $branch, $warehouse, $cashRegister, $product] = $this->fixture('premium', withProduct: true);

        $today = now();
        $yesterday = now()->subDay();
        $outOfRange = now()->subDays(40);

        $saleToday = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $company->owner_user_id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '1000']],
        ]);
        $saleToday->update(['sold_at' => $today]);

        $saleYesterday = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $company->owner_user_id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '500']],
        ]);
        $saleYesterday->update(['sold_at' => $yesterday]);

        $saleOutOfRange = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $company->owner_user_id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '9999']],
        ]);
        $saleOutOfRange->update(['sold_at' => $outOfRange]);

        $trend = app(OperationalReportService::class)->salesTrend($company, [
            'date_from' => $yesterday->format('Y-m-d'),
            'date_to' => $today->format('Y-m-d'),
        ]);

        $this->assertCount(2, $trend);
        $this->assertSame($today->format('Y-m-d'), $trend->last()['date']);
        $this->assertSame($yesterday->format('Y-m-d'), $trend->first()['date']);
        $this->assertFalse($trend->contains(fn (array $row) => $row['date'] === $outOfRange->format('Y-m-d')));
    }

    public function test_cash_register_filter_only_shows_up_with_more_than_one_register_and_scopes_sales(): void
    {
        [$owner, $company, $branch, $warehouse, $registerA, $product] = $this->fixture('pro', withProduct: true);

        // Con 1 sola caja el filtro secundario no deberia aparecer.
        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(OperationalReportsPage::class)
            ->assertDontSee('Todas las cajas');

        $registerB = app(CreateCashRegister::class)->handle($company, [
            'branch_id' => $branch->id,
            'name' => 'Caja Movil',
            'code' => 'caja-movil',
            'printer_type' => array_key_first(\App\Models\CashRegister::PRINTER_TYPES),
        ]);

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $registerA->id,
            'user_id' => $owner->id,
            'status' => \App\Enums\SaleStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '1000']],
        ]);
        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $registerB->id,
            'user_id' => $owner->id,
            'status' => \App\Enums\SaleStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '4000']],
        ]);

        // Con 2 cajas el filtro aparece y, al elegir una, los indicadores
        // solo reflejan las ventas de esa caja puntual.
        $page = Livewire::test(OperationalReportsPage::class)
            ->assertSee('Todas las cajas')
            ->set('cashRegisterId', $registerB->id);

        $cards = $page->viewData('summaryCards');
        $this->assertSame(1, $cards['sales_count']);
        $this->assertSame('4.000', $cards['sales_total']);
    }

    protected function fixture(string $planCode, bool $withProduct = false): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Reportes Gating '.$planCode.' SAS',
        ]);
        $this->assignCompanyPlan($company, $planCode);

        if (! $withProduct) {
            return [$owner, $company];
        }

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
            'name' => 'Producto Tendencia',
            'cost' => 500,
            'price_1' => 1000,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial',
            'items' => [['product_id' => $product->id, 'quantity' => '10', 'unit_cost' => '500']],
        ]);

        return [$owner, $company, $branch, $warehouse, $cashRegister, $product];
    }
}
