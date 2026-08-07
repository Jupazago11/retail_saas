<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\LoyaltyMovement;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\CashSession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoOperationalDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_demo_companies_with_operational_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([
            'Demo Basic Market SAS',
            'Demo Pro Retail SAS',
            'Demo Premium Commerce SAS',
        ] as $legalName) {
            $company = Company::query()->where('legal_name', $legalName)->firstOrFail();

            $this->assertGreaterThanOrEqual(8, Product::query()->where('company_id', $company->id)->count());
            $this->assertGreaterThanOrEqual(3, Supplier::query()->where('company_id', $company->id)->count());
            $this->assertGreaterThanOrEqual(5, Customer::query()->where('company_id', $company->id)->count());
            $this->assertGreaterThanOrEqual(3, Purchase::query()->where('company_id', $company->id)->count());
            $this->assertGreaterThanOrEqual(4, Sale::query()->where('company_id', $company->id)->count());
        }
    }

    public function test_database_seeder_creates_repeat_buyers_and_cross_company_debtors(): void
    {
        $this->seed(DatabaseSeeder::class);

        $anaCustomerCount = Customer::query()
            ->whereHas('person', fn ($query) => $query->where('document_number', '1001001001'))
            ->count();

        $jorgeDebtorCompanyCount = CreditAccount::query()
            ->where('balance_due', '>', 0)
            ->whereHas('customer.person', fn ($query) => $query->where('document_number', '1001002001'))
            ->count();

        $this->assertSame(3, $anaCustomerCount);
        $this->assertGreaterThanOrEqual(2, $jorgeDebtorCompanyCount);
    }

    public function test_database_seeder_adds_richer_demo_commercial_scenarios(): void
    {
        $this->seed(DatabaseSeeder::class);

        $basic = Company::query()->where('legal_name', 'Demo Basic Market SAS')->firstOrFail();
        $pro = Company::query()->where('legal_name', 'Demo Pro Retail SAS')->firstOrFail();
        $premium = Company::query()->where('legal_name', 'Demo Premium Commerce SAS')->firstOrFail();

        foreach ([$basic, $pro, $premium] as $company) {
            $returnedSaleCount = Sale::query()
                ->where('company_id', $company->id)
                ->where('notes', 'like', '%seed:'.$company->slug.':returned:partial%')
                ->whereIn('status', ['partially_returned', 'returned'])
                ->count();

            $cancelledSaleCount = Sale::query()
                ->where('company_id', $company->id)
                ->where('notes', 'like', '%seed:'.$company->slug.':cancelled%')
                ->where('status', 'cancelled')
                ->count();

            $historicalSessionCount = CashSession::query()
                ->where('company_id', $company->id)
                ->whereIn('status', ['closed', 'reconciled'])
                ->count();

            $this->assertSame(1, $returnedSaleCount);
            $this->assertSame(1, $cancelledSaleCount);
            $this->assertGreaterThanOrEqual(1, $historicalSessionCount);
        }

        $this->assertGreaterThanOrEqual(1, Promotion::query()->where('company_id', $pro->id)->count());
        $this->assertGreaterThanOrEqual(2, Promotion::query()->where('company_id', $premium->id)->count());

        $this->assertGreaterThanOrEqual(
            2,
            LoyaltyMovement::query()
                ->where('company_id', $pro->id)
                ->where('notes', 'like', '%seed-loyalty-%')
                ->count()
        );

        $this->assertGreaterThanOrEqual(
            2,
            LoyaltyMovement::query()
                ->where('company_id', $premium->id)
                ->where('notes', 'like', '%seed-loyalty-%')
                ->count()
        );

        $this->assertTrue(
            Sale::query()
                ->where('company_id', $pro->id)
                ->where('notes', 'seed:'.$pro->slug.':loyalty:redeem')
                ->exists()
        );

        $this->assertTrue(
            Sale::query()
                ->where('company_id', $premium->id)
                ->where('notes', 'seed:'.$premium->slug.':loyalty:redeem')
                ->exists()
        );
    }
}
