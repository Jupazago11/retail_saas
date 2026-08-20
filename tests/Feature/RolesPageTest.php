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
            ->set('roleCode', 'ops_specialist')
            ->set('displayName', 'Especialista Operativo')
            ->set('selectedPermissionCodes', ['products.view', 'purchases.view', 'suppliers.view'])
            ->call('saveRole')
            ->assertHasNoErrors();

        $role = CompanyRole::query()
            ->where('company_id', $company->id)
            ->where('code', 'ops_specialist')
            ->firstOrFail();

        $this->assertSame('Especialista Operativo', $role->display_name);
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

    public function test_roles_page_route_is_forbidden_without_roles_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles Restringidos SAS',
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
            ->get(route('admin.roles'))
            ->assertForbidden();
    }

    public function test_roles_page_can_attach_existing_user_to_company(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles Attach SAS',
        ]);
        $candidate = User::factory()->create([
            'username' => 'operario_ext',
            'email' => 'operario@example.com',
        ]);
        $role = $this->companyRolePreset($company, 'seller');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(RolesPage::class)
            ->set('newUserIdentifier', 'operario_ext')
            ->set('newUserCompanyRoleId', (string) $role->id)
            ->call('addUserToCompany')
            ->assertHasNoErrors()
            ->assertSee('operario@example.com');

        $membership = $company->users()->where('users.id', $candidate->id)->firstOrFail()->pivot;

        $this->assertSame('custom', $membership->company_role);
        $this->assertSame($role->id, $membership->company_role_id);
    }

    public function test_roles_page_blocks_attach_when_company_reaches_max_users(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles Limit SAS',
        ]);
        $employeeOne = User::factory()->create();
        $employeeTwo = User::factory()->create();
        $candidate = User::factory()->create([
            'username' => 'tercero_ext',
            'email' => 'tercero@example.com',
        ]);

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
            ->set('newUserIdentifier', 'tercero@example.com')
            ->set('newUserCompanyRoleId', (string) $sellerRole->id)
            ->call('addUserToCompany')
            ->assertHasErrors(['newUserIdentifier']);

        $this->assertNull($company->users()->where('users.id', $candidate->id)->first());
    }
}
