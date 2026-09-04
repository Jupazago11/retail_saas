<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RegisterSalePayments;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Livewire\Sales\SalesPage;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SalesPageTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_sales_page_lists_sales_and_exposes_ticket_link(): void
    {
        [$owner, $company, $sale] = $this->salesFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // La fila de la lista no muestra el nombre del producto (columna
        // "Detalle" trae fecha/sucursal/vendedor, no el detalle de lineas)
        // — eso vive en el ticket, no aqui.
        Livewire::test(SalesPage::class)
            ->assertSee('Ventas registradas')
            ->assertSee($sale->document_number)
            ->assertSee('Ver ticket');
    }

    public function test_sales_page_can_search_by_internal_document_number(): void
    {
        [$owner, $company, $sale] = $this->salesFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SalesPage::class)
            ->set('search', $sale->document_number)
            ->assertSee($sale->document_number);
    }

    public function test_sales_page_can_filter_sales_by_payment_method(): void
    {
        [$owner, $company, $cashSale] = $this->salesFixture();

        app(RegisterSalePayments::class)->handle($company, $cashSale, [
            'received_by' => $owner->id,
            'payments' => [
                ['payment_method_code' => 'cash', 'amount' => (string) $cashSale->grand_total],
            ],
        ]);

        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $product = Product::query()->where('company_id', $company->id)->firstOrFail();
        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock adicional',
            'items' => [['product_id' => $product->id, 'quantity' => '5', 'unit_cost' => '1000']],
        ]);
        $transferSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => now(),
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '3000']],
        ]);
        app(RegisterSalePayments::class)->handle($company, $transferSale, [
            'received_by' => $owner->id,
            'payments' => [
                ['payment_method_code' => 'transfer', 'amount' => (string) $transferSale->grand_total],
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SalesPage::class)
            ->assertSee($cashSale->document_number)
            ->assertSee($transferSale->document_number)
            ->set('paymentMethodFilter', 'transfer')
            ->assertDontSee($cashSale->document_number)
            ->assertSee($transferSale->document_number);
    }

    public function test_sales_page_can_filter_sales_by_date_range(): void
    {
        [$owner, $company, $todaySale] = $this->salesFixture();

        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $product = Product::query()->where('company_id', $company->id)->firstOrFail();
        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock adicional',
            'items' => [['product_id' => $product->id, 'quantity' => '5', 'unit_cost' => '1000']],
        ]);
        $oldSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => now()->subMonths(2),
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '3000']],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SalesPage::class)
            // La pagina abre con el filtro de fechas puesto en el mes en
            // curso, asi que hay que quitarlo primero para poder ver la
            // venta de hace dos meses.
            ->call('clearDateFilter')
            ->assertSee($todaySale->document_number)
            ->assertSee($oldSale->document_number)
            ->set('dateFrom', now()->subDays(1)->toDateString())
            ->assertSee($todaySale->document_number)
            ->assertDontSee($oldSale->document_number)
            ->set('dateFrom', '')
            ->set('dateTo', now()->subMonth()->toDateString())
            ->assertDontSee($todaySale->document_number)
            ->assertSee($oldSale->document_number);
    }

    public function test_sales_page_route_is_forbidden_without_sales_view_permission(): void
    {
        [, $company] = $this->salesFixture();
        $viewer = User::factory()->create();

        $company->users()->attach($viewer->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'accounting_assistant')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.index'))
            ->assertForbidden();
    }

    public function test_sales_page_can_register_partial_return_from_ui(): void
    {
        [$owner, $company, $sale] = $this->salesFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SalesPage::class)
            ->call('startReturningSale', $sale->id)
            ->set('returnItems.0.quantity', '1')
            ->set('returnReason', 'Cliente devolvio una unidad')
            ->call('registerReturn')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => SaleStatus::PartiallyReturned->value,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'id' => $sale->items->first()->id,
            'returned_quantity' => '1.000000',
        ]);
    }

    public function test_sales_page_can_cancel_draft_sale_from_ui(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Ventas Draft UI SAS',
        ]);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                [
                    'product_id' => $this->productForCompany($company)->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SalesPage::class)
            ->call('startCancellingSale', $sale->id)
            ->set('cancellationReason', 'Cliente solicito anulacion')
            ->call('cancelSaleDocument', $sale->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => SaleStatus::Cancelled->value,
        ]);
    }

    public function test_sales_page_shows_edit_action_for_draft_sales(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Ventas Draft Link SAS',
        ]);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                [
                    'product_id' => $this->productForCompany($company)->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SalesPage::class)
            ->assertSee('Editar borrador')
            ->assertSee("edit-draft-requested', { saleId: {$sale->id} }", false);
    }

    public function test_sales_page_shows_modify_action_only_for_confirmed_pos_sales(): void
    {
        [$owner, $company, $confirmedSale] = $this->salesFixture();
        $productId = $confirmedSale->items->first()->product_id;

        $draftSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $confirmedSale->branch_id,
            'warehouse_id' => $confirmedSale->warehouse_id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => '1', 'unit_price' => '1800'],
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $component = Livewire::test(SalesPage::class)
            ->assertSee("modify-sale-requested', { saleId: {$confirmedSale->id} }", false);

        $component->assertDontSee("modify-sale-requested', { saleId: {$draftSale->id} }", false);
    }

    public function test_sales_page_return_action_is_forbidden_without_sales_return_permission(): void
    {
        [, $company, $sale] = $this->salesFixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'sales_view_only_custom',
            'display_name' => 'Solo lectura ventas',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'sales.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'sales_view_only_custom',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SalesPage::class)
            ->call('startReturningSale', $sale->id)
            ->assertForbidden();
    }

    protected function salesFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Ventas UI SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $product = $this->productForCompany($company);

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

    protected function productForCompany($company): Product
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

        return Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz visual',
            'cost' => 1000,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);
    }
}
