<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Admin\BundlesPage;
use App\Models\Plan;
use App\Models\RoleTemplate;
use App\Models\Subscription;
use App\Models\SubscriptionBundle;
use App\Models\SubscriptionBundleCompany;
use App\Models\User;
use App\Services\Plans\PlanCatalogBootstrapper;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BundlesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundles_page_can_create_and_update_bundle_for_current_company(): void
    {
        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Bundle Target SAS',
        ]);
        $premiumPlan = Plan::query()->where('code', 'premium')->firstOrFail();
        $proPlan = Plan::query()->where('code', 'pro')->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(BundlesPage::class)
            ->set('name', 'Grupo Escalado')
            ->set('planId', (string) $premiumPlan->id)
            ->set('status', 'active')
            ->set('maxCompanies', '5')
            ->set('discountType', 'percentage')
            ->set('discountValue', '12.50')
            ->call('saveBundle')
            ->assertHasNoErrors()
            ->assertSee('Grupo Escalado')
            ->assertSee('Bundle Target SAS')
            ->assertSee('Premium');

        $membership = SubscriptionBundleCompany::query()
            ->where('company_id', $company->id)
            ->firstOrFail();

        $this->assertDatabaseHas('subscription_bundles', [
            'id' => $membership->bundle_id,
            'owner_user_id' => $owner->id,
            'name' => 'Grupo Escalado',
            'max_companies' => 5,
            'discount_type' => 'percentage',
            'discount_value' => '12.50',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'bundle_id' => $membership->bundle_id,
            'plan_id' => $premiumPlan->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'subscription_bundle.created',
        ]);

        Livewire::test(BundlesPage::class)
            ->call('startEditingMembership', $membership->id)
            ->set('name', 'Grupo Refinado')
            ->set('planId', (string) $proPlan->id)
            ->set('status', 'inactive')
            ->set('discountType', 'fixed_amount')
            ->set('discountValue', '15000')
            ->call('saveBundle')
            ->assertHasNoErrors()
            ->assertSee('Grupo Refinado')
            ->assertSee('Pro');

        $this->assertDatabaseHas('subscription_bundles', [
            'id' => $membership->bundle_id,
            'name' => 'Grupo Refinado',
            'status' => 'inactive',
            'discount_type' => 'fixed_amount',
            'discount_value' => '15000.00',
        ]);
        $this->assertDatabaseHas('subscription_bundle_companies', [
            'id' => $membership->id,
            'plan_id' => $proPlan->id,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'bundle_id' => $membership->bundle_id,
            'plan_id' => $proPlan->id,
            'status' => 'ended',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'subscription_bundle.updated',
        ]);
    }

    public function test_bundles_page_route_is_forbidden_without_settings_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Bundle Restringido SAS',
        ]);
        $viewer = User::factory()->create();

        $company->users()->attach($viewer->id, [
            'company_role' => 'seller',
            'role_template_id' => RoleTemplate::query()->where('code', 'seller')->value('id'),
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('admin.bundles'))
            ->assertForbidden();
    }
}
