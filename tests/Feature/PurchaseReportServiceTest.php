<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Purchases\ReturnPurchase;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\PurchaseReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchases_trend_groups_totals_by_day_within_the_filtered_range(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();

        $today = now();
        $yesterday = now()->subDay();
        $outOfRange = now()->subDays(40);

        $this->createPurchase($company, $branch, $warehouse, $product, '1000', $today);
        $this->createPurchase($company, $branch, $warehouse, $product, '500', $yesterday);
        $this->createPurchase($company, $branch, $warehouse, $product, '9999', $outOfRange);

        $trend = app(PurchaseReportService::class)->purchasesTrend($company, [
            'date_from' => $yesterday->format('Y-m-d'),
            'date_to' => $today->format('Y-m-d'),
        ]);

        $this->assertCount(2, $trend);
        $this->assertSame($today->format('Y-m-d'), $trend->last()['date']);
        $this->assertSame($yesterday->format('Y-m-d'), $trend->first()['date']);
        $this->assertFalse($trend->contains(fn (array $row) => $row['date'] === $outOfRange->format('Y-m-d')));
    }

    public function test_summary_cards_exclude_cancelled_purchases_and_track_outstanding_balance(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, ['first_name' => 'Proveedor', 'last_name' => 'Reporte']);

        $open = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '2', 'unit_cost' => '1000']],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $open, ['amount' => '500', 'payment_method_code' => 'cash']);

        $cancelled = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '5000']],
        ]);
        app(ReturnPurchase::class)->handle($company, $cancelled);

        $cards = app(PurchaseReportService::class)->summaryCards($company, [
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        // El total en dinero ignora la compra cancelada (2000, no 7000).
        $this->assertSame('2.000', $cards['purchases_total']);
        $this->assertSame('500', $cards['payments_total']);
        $this->assertSame(1, $cards['cancelled_purchases_count']);
        $this->assertSame('1.500', $cards['payables_balance_due']);
    }

    public function test_archived_purchase_payments_are_excluded_from_totals_and_breakdown(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, ['first_name' => 'Proveedor', 'last_name' => 'Archivado']);

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '3000']],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $purchase, ['amount' => '3000', 'payment_method_code' => 'cash']);

        // "Archivar" en la UI (PurchasesPage::archivePurchase) es un borrado
        // suave, solo permitido cuando el saldo ya esta en cero.
        $purchase->delete();

        $filters = [
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ];

        $cards = app(PurchaseReportService::class)->summaryCards($company, $filters);
        $this->assertSame('0', $cards['payments_total']);

        $breakdown = app(PurchaseReportService::class)->paymentMethodBreakdown($company, $filters);
        $this->assertTrue($breakdown->isEmpty());
    }

    public function test_payment_method_breakdown_differentiates_cash_and_transfer(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, ['first_name' => 'Proveedor', 'last_name' => 'Medios']);

        $purchaseA = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '3', 'unit_cost' => '1000']],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $purchaseA, ['amount' => '1000', 'payment_method_code' => 'cash']);

        $purchaseB = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '2', 'unit_cost' => '1000']],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $purchaseB, ['amount' => '2000', 'payment_method_code' => 'transfer']);

        $breakdown = app(PurchaseReportService::class)->paymentMethodBreakdown($company, [
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ])->keyBy('payment_method_code');

        $this->assertSame('1.000', $breakdown->get('cash')['payments_total']);
        $this->assertSame('Efectivo', $breakdown->get('cash')['payment_method_label']);
        $this->assertSame('2.000', $breakdown->get('transfer')['payments_total']);
        $this->assertSame('Transferencia', $breakdown->get('transfer')['payment_method_label']);
    }

    protected function createPurchase($company, $branch, $warehouse, $product, string $unitCost, $purchasedAt): void
    {
        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => $unitCost]],
        ]);
        $purchase->update(['purchased_at' => $purchasedAt]);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Reportes Compras SAS',
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
            'name' => 'Producto Reporte Compras',
            'cost' => 500,
            'price_1' => 1000,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$company, $branch, $warehouse, $product];
    }
}
