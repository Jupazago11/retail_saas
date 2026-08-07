<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Credit\CustomerImportsPage;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class CustomerImportsPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_customer_import_page_creates_credit_and_loyalty_customers_from_csv(): void
    {
        [$owner, $company] = $this->fixture();

        $file = UploadedFile::fake()->createWithContent('customers.csv', implode("\n", [
            'first_name,last_name,document_type,document_number,email,credit_enabled,credit_limit,loyalty_enabled',
            'Maria,Lopez,CC,123456,maria@demo.com,true,150000,true',
            'Carlos,Perez,CC,123456,carlos@demo.com,false,0,false',
        ]));

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CustomerImportsPage::class)
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSee('customers.csv')
            ->assertSee('Fila 3: Ya existe un cliente con el mismo documento.');

        $this->assertDatabaseHas('people', [
            'first_name' => 'Maria',
            'document_number' => '123456',
            'email' => 'maria@demo.com',
        ]);
        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'credit_enabled' => true,
            'loyalty_enabled' => true,
        ]);
        $this->assertDatabaseHas('credit_accounts', [
            'company_id' => $company->id,
            'credit_limit' => '150000.00',
            'available_credit' => '150000.00',
        ]);
        $this->assertDatabaseHas('loyalty_accounts', [
            'company_id' => $company->id,
            'points_balance' => '0.0000',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'customers.imported',
        ]);
    }

    public function test_customer_import_route_is_forbidden_without_credit_manage_permission(): void
    {
        [, $company] = $this->fixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'credit_view_only_customers',
            'display_name' => 'Solo vista credito',
            'status' => RecordStatus::Active->value,
        ]);
        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'credit.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => $companyRole->code,
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('credit.customers.imports'))
            ->assertForbidden();
    }

    protected function fixture(string $planCode = 'pro'): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Clientes CSV SAS',
        ]);
        $this->assignCompanyPlan($company, $planCode);

        return [$owner, $company];
    }
}
