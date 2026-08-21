<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Admin\CompanyStructurePage;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class CompanyStructurePageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_structure_page_can_create_branch_warehouse_and_cash_register_when_plan_allows_it(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Estructura Premium SAS',
        ]);
        $this->assignCompanyPlan($company, 'premium');
        $primaryBranch = $company->branches()->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CompanyStructurePage::class)
            ->set('branchName', 'Sucursal Norte')
            ->set('branchCode', 'norte')
            ->call('saveBranch')
            ->assertHasNoErrors()
            ->assertSee('Sucursal Norte')
            ->set('warehouseBranchId', $primaryBranch->id)
            ->set('warehouseName', 'Bodega Norte')
            ->set('warehouseCode', 'bod_norte')
            ->call('saveWarehouse')
            ->assertHasNoErrors()
            ->assertSee('Bodega Norte')
            ->set('cajaBranchId', $primaryBranch->id)
            ->set('cajaName', 'Caja Norte')
            ->set('cajaCode', 'caja_norte')
            ->set('cajaPrinterType', 'thermal_58mm')
            ->call('saveCashRegister')
            ->assertHasNoErrors()
            ->assertSee('Caja Norte');

        $this->assertDatabaseHas('branches', [
            'company_id' => $company->id,
            'name' => 'Sucursal Norte',
            'code' => 'NORTE',
        ]);
        $this->assertDatabaseHas('warehouses', [
            'company_id' => $company->id,
            'name' => 'Bodega Norte',
            'code' => 'BOD_NORTE',
        ]);
        $this->assertDatabaseHas('cash_registers', [
            'company_id' => $company->id,
            'name' => 'Caja Norte',
            'code' => 'CAJA_NORTE',
        ]);
    }

    public function test_structure_page_blocks_new_records_when_basic_plan_reaches_limits(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Estructura Basic SAS',
        ]);
        $primaryBranch = $company->branches()->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CompanyStructurePage::class)
            ->set('branchName', 'Sucursal Dos')
            ->set('branchCode', 'dos')
            ->call('saveBranch')
            ->assertHasErrors(['branchName'])
            ->set('warehouseBranchId', $primaryBranch->id)
            ->set('warehouseName', 'Bodega Dos')
            ->set('warehouseCode', 'bod_dos')
            ->call('saveWarehouse')
            ->assertHasErrors(['warehouseName'])
            ->set('cajaBranchId', $primaryBranch->id)
            ->set('cajaName', 'Caja Dos')
            ->set('cajaCode', 'caja_dos')
            ->set('cajaPrinterType', 'thermal_58mm')
            ->call('saveCashRegister')
            ->assertHasErrors(['cajaName']);

        $this->assertDatabaseMissing('branches', [
            'company_id' => $company->id,
            'name' => 'Sucursal Dos',
        ]);
        $this->assertDatabaseMissing('warehouses', [
            'company_id' => $company->id,
            'name' => 'Bodega Dos',
        ]);
        $this->assertDatabaseMissing('cash_registers', [
            'company_id' => $company->id,
            'name' => 'Caja Dos',
        ]);
    }

    public function test_structure_page_route_is_forbidden_without_settings_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Estructura Restringida SAS',
        ]);
        $viewer = User::factory()->create();

        $company->users()->attach($viewer->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'seller')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('admin.structure'))
            ->assertForbidden();
    }
}
