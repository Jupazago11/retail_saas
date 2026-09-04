<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Admin\RolesPage;
use App\Models\CompanyRole;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class RolesPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_roles_page_can_create_company_role_and_assign_it_to_user(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles UI SAS',
        ]);
        $employee = User::factory()->create();

        $company->users()->attach($employee->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'seller')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(RolesPage::class)
            ->set('displayName', 'Especialista Operativo')
            ->set('selectedPermissionCodes', ['products.view', 'purchases.view', 'suppliers.view'])
            ->call('saveRole')
            ->assertHasNoErrors();

        $role = CompanyRole::query()
            ->where('company_id', $company->id)
            ->where('display_name', 'Especialista Operativo')
            ->firstOrFail();

        $this->assertSame('ESPECIALISTA_OPERATIVO', $role->code);
        $this->assertSame(RecordStatus::Active->value, $role->status);
        $this->assertEqualsCanonicalizing(
            ['products.view', 'purchases.view', 'suppliers.view'],
            $role->permissions()->pluck('code')->all()
        );

        Livewire::test(RolesPage::class)
            ->set("memberships.{$employee->id}.company_role_id", (string) $role->id)
            ->call('saveMembership', $employee->id)
            ->assertHasNoErrors();

        $membership = $company->users()->where('users.id', $employee->id)->firstOrFail()->pivot;

        $this->assertSame($role->id, $membership->company_role_id);
        $this->assertSame('custom', $membership->company_role);
    }

    public function test_roles_page_auto_generates_unique_code_on_name_collision(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles Codigo SAS',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(RolesPage::class)
            ->set('displayName', 'Supervisor')
            ->set('selectedPermissionCodes', ['products.view'])
            ->call('saveRole')
            ->assertHasNoErrors();

        Livewire::test(RolesPage::class)
            ->set('displayName', 'Supervisor')
            ->set('selectedPermissionCodes', ['products.view'])
            ->call('saveRole')
            ->assertHasNoErrors();

        $codes = CompanyRole::query()
            ->where('company_id', $company->id)
            ->where('display_name', 'Supervisor')
            ->pluck('code')
            ->all();

        $this->assertCount(2, $codes);
        $this->assertEqualsCanonicalizing(['SUPERVISOR', 'SUPERVISOR_2'], $codes);
    }

    public function test_roles_page_can_toggle_role_status(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles Toggle SAS',
        ]);
        $role = $this->companyRolePreset($company, 'seller');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $this->assertSame(RecordStatus::Active->value, $role->status);

        Livewire::test(RolesPage::class)
            ->call('toggleRoleStatus', $role->id)
            ->assertHasNoErrors();

        $this->assertSame(RecordStatus::Inactive->value, $role->fresh()->status);

        Livewire::test(RolesPage::class)
            ->call('toggleRoleStatus', $role->id)
            ->assertHasNoErrors();

        $this->assertSame(RecordStatus::Active->value, $role->fresh()->status);
    }

    public function test_roles_page_route_is_forbidden_without_roles_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles Restringidos SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');
        $viewer = User::factory()->create();

        $company->users()->attach($viewer->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'seller')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('admin.roles'))
            ->assertForbidden();
    }

    public function test_roles_page_can_create_internal_user_and_attach_to_company(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles Internal User SAS',
        ]);
        $role = $this->companyRolePreset($company, 'seller');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(RolesPage::class)
            ->set('newInternalName', 'Laura Gomez')
            ->set('newInternalUsername', 'laura_g')
            ->set('newInternalPassword', 'password123')
            ->set('newInternalCompanyRoleId', (string) $role->id)
            ->call('createInternalUser')
            ->assertHasNoErrors()
            ->assertSee('laura_g');

        $user = User::query()->where('username', 'laura_g')->firstOrFail();
        $membership = $company->users()->where('users.id', $user->id)->firstOrFail()->pivot;

        $this->assertSame('custom', $membership->company_role);
        $this->assertSame($role->id, $membership->company_role_id);
    }

    public function test_roles_page_blocks_creating_internal_user_when_company_reaches_max_users(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles Limit SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');
        $employeeOne = User::factory()->create();
        $employeeTwo = User::factory()->create();

        $sellerRole = $this->companyRolePreset($company, 'seller');

        $company->users()->attach($employeeOne->id, [
            'company_role' => 'custom',
            'company_role_id' => $sellerRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);
        $company->users()->attach($employeeTwo->id, [
            'company_role' => 'custom',
            'company_role_id' => $sellerRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(RolesPage::class)
            ->set('newInternalName', 'Tercero Externo')
            ->set('newInternalUsername', 'tercero_ext')
            ->set('newInternalPassword', 'password123')
            ->set('newInternalCompanyRoleId', (string) $sellerRole->id)
            ->call('createInternalUser')
            ->assertHasErrors(['newInternalUsername']);

        $this->assertNull(User::query()->where('username', 'tercero_ext')->first());
    }
}
