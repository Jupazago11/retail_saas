<?php

namespace Tests\Feature;

use App\Actions\Audit\ListAuditLogs;
use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RegisterSalePayments;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_audit_logs_by_action_actor_and_entity(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->fixture();

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
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

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

        $closedCashSession = app(CloseCashSession::class)->handle($company, $cashSession, [
            'closed_by' => $owner->id,
            'closing_counted_amount' => '51800',
        ]);

        $query = app(ListAuditLogs::class);

        $actionRows = $query->handle($company, [
            'action' => 'cash_session.closed',
        ]);

        $this->assertCount(1, $actionRows);
        $this->assertSame($closedCashSession->id, $actionRows->first()->auditable_id);

        $actorRows = $query->handle($company, [
            'actor_user_id' => $owner->id,
        ]);

        $this->assertGreaterThanOrEqual(4, $actorRows->count());
        $this->assertTrue($actorRows->every(fn ($log) => $log->actor_user_id === $owner->id));

        $entityRows = $query->handle($company, [
            'auditable_type' => 'App\\Models\\Sale',
            'auditable_id' => $sale->id,
        ]);

        $this->assertCount(1, $entityRows);
        $this->assertSame('sale.created', $entityRows->first()->action);
    }

    public function test_it_filters_audit_logs_by_date_range(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->fixture();

        $firstLogMoment = now()->subDays(3);
        $secondLogMoment = now()->subDay();

        $this->travelTo($firstLogMoment);
        app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $this->travelTo($secondLogMoment);
        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock posterior',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);
        $this->travelBack();

        $rows = app(ListAuditLogs::class)->handle($company, [
            'date_from' => now()->subDays(2)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('inventory.adjustment.created', $rows->first()->action);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Consulta Auditoria SAS',
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
