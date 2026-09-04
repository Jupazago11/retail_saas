<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Purchases\ApplySupplierCreditToPurchase;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\ListSupplierPayablesSummary;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Purchases\ReturnPurchase;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPayablesSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_open_balance_credit_and_operational_dates_per_supplier(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $alpha = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Alpha',
        ]);
        $beta = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Beta',
        ]);
        $gamma = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Gamma',
        ]);

        $this->travelTo(now()->startOfDay()->subDays(2));
        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $alpha->id,
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '2',
                'unit_cost' => '1000',
            ]],
        ]);
        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $alpha->id,
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDays(100)->format('Y-m-d H:i:s'),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '3',
                'unit_cost' => '1000',
            ]],
        ]);

        $this->travelTo(now()->addDay());
        $creditSource = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $beta->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_cost' => '1000',
            ]],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $creditSource, [
            'amount' => '1000',
        ]);
        app(ReturnPurchase::class)->handle($company, $creditSource);

        $openBetaPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $beta->id,
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '2',
                'unit_cost' => '1000',
            ]],
        ]);
        app(ApplySupplierCreditToPurchase::class)->handle($company, $openBetaPurchase, [
            'amount' => '1000',
            'reference' => 'APLICACION-SALDO-BETA',
        ]);

        $gammaCreditSource = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $gamma->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_cost' => '1000',
            ]],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $gammaCreditSource, [
            'amount' => '1000',
        ]);
        app(ReturnPurchase::class)->handle($company, $gammaCreditSource);

        $rows = app(ListSupplierPayablesSummary::class)->handle($company);

        $this->assertCount(3, $rows);
        $this->assertSame([$alpha->id, $beta->id, $gamma->id], $rows->pluck('supplier_id')->all());

        $alphaRow = $rows->firstWhere('supplier_id', $alpha->id);
        $betaRow = $rows->firstWhere('supplier_id', $beta->id);
        $gammaRow = $rows->firstWhere('supplier_id', $gamma->id);

        $this->assertSame('5000.00', $alphaRow['open_balance_total']);
        $this->assertSame('0.00', $alphaRow['current_balance_total']);
        $this->assertSame('5000.00', $alphaRow['overdue_balance_total']);
        $this->assertSame('2000.00', $alphaRow['age_0_30_balance_total']);
        $this->assertSame('0.00', $alphaRow['age_31_60_balance_total']);
        $this->assertSame('0.00', $alphaRow['age_61_90_balance_total']);
        $this->assertSame('3000.00', $alphaRow['age_91_plus_balance_total']);
        $this->assertSame('5000.00', $alphaRow['net_balance_exposure']);
        $this->assertSame(2, $alphaRow['open_purchases_count']);
        $this->assertSame(2, $alphaRow['overdue_purchases_count']);
        $this->assertNotNull($alphaRow['next_due_at']);
        $this->assertNotNull($alphaRow['last_movement_at']);

        $this->assertSame('1000.00', $betaRow['open_balance_total']);
        $this->assertSame('1000.00', $betaRow['current_balance_total']);
        $this->assertSame('0.00', $betaRow['overdue_balance_total']);
        $this->assertSame('0.00', $betaRow['age_0_30_balance_total']);
        $this->assertSame('0.00', $betaRow['age_31_60_balance_total']);
        $this->assertSame('0.00', $betaRow['age_61_90_balance_total']);
        $this->assertSame('0.00', $betaRow['age_91_plus_balance_total']);
        $this->assertSame('0.00', $betaRow['credit_balance']);
        $this->assertSame('1000.00', $betaRow['net_balance_exposure']);
        $this->assertSame(1, $betaRow['open_purchases_count']);
        $this->assertSame(0, $betaRow['overdue_purchases_count']);
        $this->assertNotNull($betaRow['last_movement_at']);
        $this->assertNotNull($betaRow['next_due_at']);

        $this->assertSame('0.00', $gammaRow['open_balance_total']);
        $this->assertSame('0.00', $gammaRow['current_balance_total']);
        $this->assertSame('0.00', $gammaRow['overdue_balance_total']);
        $this->assertSame('0.00', $gammaRow['age_0_30_balance_total']);
        $this->assertSame('0.00', $gammaRow['age_31_60_balance_total']);
        $this->assertSame('0.00', $gammaRow['age_61_90_balance_total']);
        $this->assertSame('0.00', $gammaRow['age_91_plus_balance_total']);
        $this->assertSame('1000.00', $gammaRow['credit_balance']);
        $this->assertSame('-1000.00', $gammaRow['net_balance_exposure']);
        $this->assertSame(0, $gammaRow['open_purchases_count']);
    }

    public function test_it_filters_supplier_summary_by_name_balance_and_overdue_status(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $alpha = app(CreateSupplier::class)->handle($company, [
            'document_number' => '900123',
            'first_name' => 'Proveedor',
            'last_name' => 'Alpha',
        ]);
        $gamma = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Gamma',
        ]);

        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $alpha->id,
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_cost' => '1000',
            ]],
        ]);

        $gammaPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $gamma->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_cost' => '1000',
            ]],
        ]);
        $gammaPurchase = app(RegisterPurchasePayment::class)->handle($company, $gammaPurchase, [
            'amount' => '1000',
        ]);
        app(ReturnPurchase::class)->handle($company, $gammaPurchase);

        $nameRows = app(ListSupplierPayablesSummary::class)->handle($company, [
            'supplier_name' => '900123',
        ]);
        $balanceRows = app(ListSupplierPayablesSummary::class)->handle($company, [
            'has_balance_only' => true,
        ]);
        $overdueRows = app(ListSupplierPayablesSummary::class)->handle($company, [
            'overdue_only' => true,
        ]);
        $creditRows = app(ListSupplierPayablesSummary::class)->handle($company, [
            'has_credit_only' => true,
        ]);

        $this->assertCount(1, $nameRows);
        $this->assertSame($alpha->id, $nameRows->first()['supplier_id']);

        $this->assertCount(1, $balanceRows);
        $this->assertSame($alpha->id, $balanceRows->first()['supplier_id']);

        $this->assertCount(1, $overdueRows);
        $this->assertSame($alpha->id, $overdueRows->first()['supplier_id']);

        $this->assertCount(1, $creditRows);
        $this->assertSame($gamma->id, $creditRows->first()['supplier_id']);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Resumen Proveedores SAS',
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
            'name' => 'Arroz',
            'cost' => 900,
            'price_1' => 1300,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$company, $branch, $warehouse, $product];
    }
}
