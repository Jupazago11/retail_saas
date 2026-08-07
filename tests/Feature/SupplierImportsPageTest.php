<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Purchases\SupplierImportsPage;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SupplierImportsPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_supplier_import_page_creates_valid_rows_and_reports_duplicates(): void
    {
        [$owner, $company] = $this->fixture();

        $file = UploadedFile::fake()->createWithContent('suppliers.csv', implode("\n", [
            'first_name,last_name,document_type,document_number,email,status,payment_term_days,notes',
            'Proveedor Uno,SAS,NIT,900111222,uno@demo.com,active,30,Mayorista',
            'Proveedor Dos,LTDA,NIT,900111222,dos@demo.com,active,15,Duplicado documento',
        ]));

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SupplierImportsPage::class)
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSee('suppliers.csv')
            ->assertSee('Fila 3: Ya existe un proveedor con el mismo documento.');

        $this->assertDatabaseHas('suppliers', [
            'company_id' => $company->id,
            'status' => RecordStatus::Active->value,
            'payment_term_days' => 30,
        ]);

        $this->assertDatabaseHas('people', [
            'first_name' => 'Proveedor Uno',
            'document_number' => '900111222',
            'email' => 'uno@demo.com',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'suppliers.imported',
        ]);
    }

    public function test_supplier_import_route_is_forbidden_without_manage_permission(): void
    {
        [, $company] = $this->fixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'supplier_view_only',
            'display_name' => 'Solo consulta proveedores',
            'status' => RecordStatus::Active->value,
        ]);
        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'suppliers.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => $companyRole->code,
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('purchases.suppliers.imports'))
            ->assertForbidden();
    }

    public function test_supplier_import_route_is_forbidden_when_plan_has_no_import_feature(): void
    {
        [$owner, $company] = $this->fixture('basic');

        $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('purchases.suppliers.imports'))
            ->assertForbidden();
    }

    protected function fixture(string $planCode = 'pro'): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Proveedores CSV SAS',
        ]);
        $this->assignCompanyPlan($company, $planCode);

        return [$owner, $company];
    }
}
