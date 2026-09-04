<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Purchases\PurchasesPage;
use App\Livewire\Purchases\SuppliersPage;
use App\Livewire\Products\ProductsPage;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class PermissionAuthorizationTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_seller_can_access_products_but_not_masters(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Retail Permisos SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');

        $seller = User::factory()->create();

        $company->users()->attach($seller->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'seller')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($seller)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('products.index'))
            ->assertOk();

        $this->actingAs($seller)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('masters.categories'))
            ->assertForbidden();
    }

    public function test_product_create_action_is_forbidden_for_view_only_company_role(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Retail Viewer SAS',
        ]);

        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'catalog_viewer',
            'display_name' => 'Visualizador de catalogo',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'products.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'catalog_viewer',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(ProductsPage::class)
            ->call('saveProduct')
            ->assertForbidden();
    }

    public function test_supplier_manage_action_is_forbidden_for_view_only_company_role(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Retail Proveedores SAS',
        ]);

        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'suppliers_viewer',
            'display_name' => 'Visualizador de proveedores',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'suppliers.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'suppliers_viewer',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SuppliersPage::class)
            ->call('saveSupplier')
            ->assertForbidden();
    }

    public function test_purchase_payment_action_is_forbidden_without_payables_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Retail Compras Permisos SAS',
        ]);

        $purchasingManager = User::factory()->create();

        $company->users()->attach($purchasingManager->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'purchasing_manager')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($purchasingManager);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->call('registerPayment')
            ->assertForbidden();
    }
}
