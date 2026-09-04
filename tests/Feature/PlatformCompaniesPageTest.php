<?php

namespace Tests\Feature;

use App\Livewire\Platform\CompaniesPage;
use App\Models\BusinessType;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\User;
use App\Services\Authorization\AuthorizationCatalogBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class PlatformCompaniesPageTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_confirm_type_modal_activates_a_pending_company_with_business_type(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_type_id' => null]);
        $subscription = $company->subscriptions()->create([
            'plan_id' => null,
            'bundle_id' => null,
            'status' => 'pending',
        ]);
        $restaurant = BusinessType::query()->where('code', 'restaurant')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(CompaniesPage::class)
            ->call('openActivationModal', $company->id)
            ->set('selectedBusinessTypeId', $restaurant->id)
            ->call('confirmTypeModal')
            ->assertHasNoErrors();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->starts_at);
        $this->assertSame($restaurant->id, $company->fresh()->business_type_id);
    }

    public function test_activating_a_company_as_restaurant_provisions_default_roles(): void
    {
        app(AuthorizationCatalogBootstrapper::class)->ensureDefaults();

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_type_id' => null]);
        $company->subscriptions()->create([
            'plan_id' => null,
            'bundle_id' => null,
            'status' => 'pending',
        ]);
        $restaurant = BusinessType::query()->where('code', 'restaurant')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(CompaniesPage::class)
            ->call('openActivationModal', $company->id)
            ->set('selectedBusinessTypeId', $restaurant->id)
            ->call('confirmTypeModal')
            ->assertHasNoErrors();

        $roles = CompanyRole::query()->where('company_id', $company->id)->with('permissions')->get()->keyBy('code');

        $this->assertCount(3, $roles);
        $this->assertSame('Cajero', $roles['CAJERO']->display_name);
        $this->assertSame('Mesero', $roles['MESERO']->display_name);
        $this->assertSame('Cocina', $roles['COCINA']->display_name);
        $this->assertEqualsCanonicalizing(['sales.view', 'sales.create', 'dining.orders', 'kitchen.manage'], $roles['CAJERO']->permissions->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['dining.orders'], $roles['MESERO']->permissions->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['kitchen.manage'], $roles['COCINA']->permissions->pluck('code')->all());
    }

    public function test_activating_a_company_as_restaurant_provisions_one_user_per_default_role(): void
    {
        app(AuthorizationCatalogBootstrapper::class)->ensureDefaults();

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_type_id' => null]);
        $company->users()->attach($owner->id, ['company_role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $company->subscriptions()->create([
            'plan_id' => null,
            'bundle_id' => null,
            'status' => 'pending',
        ]);
        $restaurant = BusinessType::query()->where('code', 'restaurant')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(CompaniesPage::class)
            ->call('openActivationModal', $company->id)
            ->set('selectedBusinessTypeId', $restaurant->id)
            ->call('confirmTypeModal')
            ->assertHasNoErrors();

        foreach (['cajero', 'mesero', 'cocina'] as $prefix) {
            $expectedUsername = "{$prefix}.{$company->id}";
            $user = User::query()->where('username', $expectedUsername)->first();

            $this->assertNotNull($user, "Se esperaba un usuario con username {$expectedUsername}");
            $this->assertTrue($user->must_change_password);
            $this->assertTrue(Hash::check($expectedUsername, $user->password));
        }
    }

    public function test_activating_a_company_as_general_does_not_provision_restaurant_roles(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_type_id' => null]);
        $company->subscriptions()->create([
            'plan_id' => null,
            'bundle_id' => null,
            'status' => 'pending',
        ]);
        $general = BusinessType::query()->where('code', 'general')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(CompaniesPage::class)
            ->call('openActivationModal', $company->id)
            ->set('selectedBusinessTypeId', $general->id)
            ->call('confirmTypeModal')
            ->assertHasNoErrors();

        $this->assertSame(0, CompanyRole::query()->where('company_id', $company->id)->count());
    }

    public function test_activation_requires_a_business_type(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_type_id' => null]);
        $subscription = $company->subscriptions()->create([
            'plan_id' => null,
            'bundle_id' => null,
            'status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(CompaniesPage::class)
            ->call('openActivationModal', $company->id)
            ->call('confirmTypeModal')
            ->assertHasErrors('selectedBusinessTypeId');

        $this->assertSame('pending', $subscription->fresh()->status);
        $this->assertNull($company->fresh()->business_type_id);
    }

    public function test_platform_admin_can_change_business_type_of_an_active_company(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $general = BusinessType::query()->where('code', 'general')->firstOrFail();
        $restaurant = BusinessType::query()->where('code', 'restaurant')->firstOrFail();
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_type_id' => $general->id]);
        $subscription = $company->subscriptions()->create([
            'plan_id' => null,
            'bundle_id' => null,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(CompaniesPage::class)
            ->call('openTypeEditModal', $company->id)
            ->set('selectedBusinessTypeId', $restaurant->id)
            ->call('confirmTypeModal')
            ->assertHasNoErrors();

        $this->assertSame($restaurant->id, $company->fresh()->business_type_id);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_suspend_company_suspends_an_active_company(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $subscription = $company->subscriptions()->create([
            'plan_id' => null,
            'bundle_id' => null,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(CompaniesPage::class)->call('suspendCompany', $company->id);

        $this->assertSame('pending', $subscription->fresh()->status);
    }

    public function test_non_platform_admin_cannot_access_the_page(): void
    {
        $viewer = User::factory()->create(['is_platform_admin' => false]);
        $this->actingAs($viewer);

        Livewire::test(CompaniesPage::class)->assertForbidden();
    }
}
