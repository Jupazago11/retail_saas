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
use App\Livewire\Admin\AuditLogsPage;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_page_can_filter_and_expand_snapshots(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->fixture();

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '5',
                'unit_cost' => '1000',
            ]],
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
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '1800',
            ]],
        ]);

        app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [[
                'payment_method_code' => 'cash',
                'amount' => '1800',
            ]],
        ]);

        $closedCashSession = app(CloseCashSession::class)->handle($company, $cashSession, [
            'closed_by' => $owner->id,
            'closing_counted_amount' => '51800',
        ]);
        $logId = AuditLog::query()
            ->where('company_id', $company->id)
            ->where('action', 'cash_session.closed')
            ->where('auditable_id', $closedCashSession->id)
            ->value('id');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(AuditLogsPage::class)
            ->set('action', 'cash_session.closed')
            ->assertSee('cash_session.closed')
            ->assertSee('1 eventos filtrados')
            ->call('toggleLog', $logId)
            ->assertSee('closing_counted_amount')
            ->assertSee('51800');

        $this->get(route('admin.audit-logs.export', ['action' => 'cash_session.closed']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_audit_logs_page_route_is_forbidden_without_settings_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Auditoria Restringida SAS',
        ]);

        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'reports_viewer',
            'display_name' => 'Visualizador',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'reports.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'reports_viewer',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('admin.audit-logs'))
            ->assertForbidden();
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Auditoria UI SAS',
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

        return [$owner, $company, $branch, $warehouse, $cashRegister, $product];
    }
}
