<?php

namespace Tests\Feature;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Promotions\CreatePromotion;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RegisterSalePayments;
use App\Actions\Sales\ReturnSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionTargetType;
use App\Enums\PromotionType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_it_records_audit_logs_for_critical_create_and_close_actions(): void
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

        app(CloseCashSession::class)->handle($company, $cashSession, [
            'closed_by' => $owner->id,
            'closing_counted_amount' => '51800',
        ]);

        app(CreatePromotion::class)->handle($company, [
            'name' => 'Promo arroz 10',
            'promotion_type' => PromotionType::ProductDiscount->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '10',
            'targets' => [
                [
                    'target_type' => PromotionTargetType::Product->value,
                    'target_id' => $product->id,
                    'min_quantity' => '1',
                ],
            ],
        ]);

        $this->assertDatabaseCount('audit_logs', 6);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'inventory.adjustment.created',
            'auditable_type' => 'App\\Models\\InventoryAdjustment',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'actor_user_id' => $owner->id,
            'action' => 'cash_session.opened',
            'auditable_type' => 'App\\Models\\CashSession',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'actor_user_id' => $owner->id,
            'action' => 'sale.created',
            'auditable_type' => 'App\\Models\\Sale',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'actor_user_id' => $owner->id,
            'action' => 'sale.payment_registered',
            'auditable_type' => 'App\\Models\\Payment',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'promotion.created',
            'auditable_type' => 'App\\Models\\Promotion',
        ]);

        $closeLog = AuditLog::query()
            ->where('action', 'cash_session.closed')
            ->firstOrFail();

        $this->assertSame('open', $closeLog->before_snapshot['status']);
        // El conteo (51800) coincide exacto con lo esperado (50000 + 1800),
        // asi que el cierre queda "reconciled", no "closed" con diferencia.
        $this->assertSame('reconciled', $closeLog->after_snapshot['status']);
        $this->assertSame('50000.00', $closeLog->before_snapshot['opening_amount']);
        $this->assertSame('51800.00', $closeLog->after_snapshot['closing_counted_amount']);
    }

    public function test_it_records_before_and_after_snapshots_for_sale_return(): void
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

        app(ReturnSale::class)->handle($company, $sale, [
            [
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '1',
            ],
        ], 'Cliente no lo quiso');

        $log = AuditLog::query()
            ->where('action', 'sale.returned')
            ->firstOrFail();

        $this->assertSame((string) $sale->id, (string) $log->auditable_id);
        $this->assertSame('confirmed', $log->before_snapshot['status']);
        $this->assertSame('partially_returned', $log->after_snapshot['status']);
        $this->assertNull($log->before_snapshot['returned_at']);
        $this->assertNotNull($log->after_snapshot['returned_at']);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Auditoria Retail SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
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
