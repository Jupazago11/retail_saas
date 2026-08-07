<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\ListPurchasePayables;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePayablesQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_purchase_payables_by_supplier_status_and_overdue(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();

        $overdue = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Proveedor Uno',
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDays(2),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $partial = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Proveedor Dos',
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->addDays(3),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '4',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $partial = app(RegisterPurchasePayment::class)->handle($company, $partial, [
            'amount' => '1000',
        ]);

        $paid = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Proveedor Dos',
            'status' => PurchaseStatus::Paid->value,
            'paid_amount' => '3000',
            'due_at' => now()->addDays(1),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '3',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $query = app(ListPurchasePayables::class);

        $overdueRows = $query->handle($company, [
            'overdue_only' => true,
        ]);

        $this->assertCount(1, $overdueRows);
        $this->assertSame($overdue->id, $overdueRows->first()->id);

        $openRows = $query->handle($company, [
            'status' => 'open',
        ]);

        $this->assertCount(2, $openRows);
        $this->assertSame([$overdue->id, $partial->id], $openRows->pluck('id')->all());

        $supplierRows = $query->handle($company, [
            'supplier_name' => 'Proveedor Dos',
        ]);

        $this->assertCount(2, $supplierRows);
        $this->assertSame([$partial->id, $paid->id], $supplierRows->pluck('id')->sort()->values()->all());
    }

    public function test_it_filters_purchase_payables_by_due_date_range(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();

        $first = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Proveedor A',
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => '2026-06-20 10:00:00',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $second = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Proveedor B',
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => '2026-06-25 10:00:00',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $rows = app(ListPurchasePayables::class)->handle($company, [
            'due_from' => '2026-06-21',
            'due_to' => '2026-06-30',
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame($second->id, $rows->first()->id);
        $this->assertNotSame($first->id, $rows->first()->id);
    }

    public function test_it_filters_purchase_payables_by_supplier_id(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $supplierA = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'A',
        ]);
        $supplierB = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'B',
        ]);

        $expected = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplierA->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplierB->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $rows = app(ListPurchasePayables::class)->handle($company, [
            'supplier_id' => $supplierA->id,
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame($expected->id, $rows->first()->id);
        $this->assertSame($supplierA->id, $rows->first()->supplier_id);
    }

    public function test_it_filters_purchase_payables_by_credit_supplier_and_aging_bucket(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $supplierWithCredit = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Credito',
        ]);
        $supplierWithoutCredit = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Normal',
        ]);

        $creditSource = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplierWithCredit->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_cost' => '1000',
            ]],
        ]);
        $creditSource = app(RegisterPurchasePayment::class)->handle($company, $creditSource, [
            'amount' => '1000',
        ]);
        app(\App\Actions\Purchases\ReturnPurchase::class)->handle($company, $creditSource);

        $expected = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplierWithCredit->id,
            'invoice_number' => 'FAC-CREDIT-AGE',
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDays(10)->format('Y-m-d H:i:s'),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '2',
                'unit_cost' => '1000',
            ]],
        ]);

        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplierWithoutCredit->id,
            'invoice_number' => 'FAC-NORMAL-AGE',
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDays(45)->format('Y-m-d H:i:s'),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '2',
                'unit_cost' => '1000',
            ]],
        ]);

        $rows = app(ListPurchasePayables::class)->handle($company, [
            'has_credit_only' => true,
            'aging_bucket' => '0_30',
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame($expected->id, $rows->first()->id);
        $this->assertSame($supplierWithCredit->id, $rows->first()->supplier_id);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Consulta CxP SAS',
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
