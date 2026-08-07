<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Livewire\Admin\RolesPage;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class RolesPagePermissionGatingTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_basic_plan_hides_permission_groups_for_modules_it_does_not_include(): void
    {
        [$owner, $company] = $this->fixture('basic');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $groups = Livewire::test(RolesPage::class)->instance()->permissionGroups();

        $this->assertArrayNotHasKey('credit', $groups);
        $this->assertArrayNotHasKey('loyalty', $groups);
        $this->assertArrayNotHasKey('promotions', $groups);
        // Core/administrative groups are never plan-gated.
        $this->assertArrayHasKey('masters', $groups);
        $this->assertArrayHasKey('settings', $groups);
        $this->assertArrayHasKey('roles', $groups);
        $this->assertArrayHasKey('users', $groups);
        $this->assertArrayHasKey('subscriptions', $groups);
        $this->assertArrayHasKey('suppliers', $groups);
        $this->assertArrayHasKey('payables', $groups);
        // Basic's own modules (per PlanCatalog: products, pos, cash, reports) still show.
        $this->assertArrayHasKey('products', $groups);
        $this->assertArrayHasKey('cash', $groups);
        $this->assertArrayHasKey('sales', $groups);
        $this->assertArrayHasKey('reports', $groups);
        // Basic doesn't include inventory or purchases, so those are gated too.
        $this->assertArrayNotHasKey('inventory', $groups);
        $this->assertArrayNotHasKey('purchases', $groups);
    }

    public function test_premium_plan_shows_all_permission_groups(): void
    {
        [$owner, $company] = $this->fixture('premium');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $groups = Livewire::test(RolesPage::class)->instance()->permissionGroups();

        $this->assertArrayHasKey('credit', $groups);
        $this->assertArrayHasKey('loyalty', $groups);
        $this->assertArrayHasKey('promotions', $groups);
        $this->assertArrayHasKey('inventory', $groups);
        $this->assertArrayHasKey('purchases', $groups);
    }

    public function test_editing_a_role_preserves_a_permission_from_a_now_gated_module(): void
    {
        [$owner, $company] = $this->fixture('premium');

        $role = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'credit_role',
            'display_name' => 'Rol con credito',
            'status' => 'active',
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('code', ['credit.view', 'products.view'])->pluck('id')
        );

        // Downgrade the company to a plan without credit.
        $this->assignCompanyPlan($company, 'basic');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(RolesPage::class)
            ->call('editRole', $role->id)
            ->assertSet('selectedPermissionCodes', fn (array $codes) => in_array('credit.view', $codes, true))
            ->call('saveRole')
            ->assertHasNoErrors();

        $this->assertTrue($role->fresh()->permissions->contains('code', 'credit.view'));
    }

    protected function fixture(string $planCode): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Roles Gating '.$planCode.' SAS',
        ]);
        $this->assignCompanyPlan($company, $planCode);

        return [$owner, $company];
    }
}
