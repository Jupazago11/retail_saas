<?php

namespace Tests\Feature;

use App\Livewire\Platform\PlansPage;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Plans\PlanCatalogBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlansPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PlanCatalogBootstrapper::class)->ensureDefaults();
    }

    public function test_platform_admin_can_edit_modules_features_and_limits_for_a_plan(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platformAdmin);

        $basic = Plan::query()->where('code', 'basic')->firstOrFail();
        $posModule = Module::query()->where('code', 'pos')->firstOrFail();
        $frozenSalesFeature = Feature::query()->where('code', 'pos.frozen_sales')->firstOrFail();

        Livewire::test(PlansPage::class)
            ->call('startEdit', $basic->id)
            ->call('toggleModule', $posModule->id) // pos is enabled by default on basic -> disable it
            ->call('toggleFeature', $frozenSalesFeature->id) // ignored: pos module is now off
            ->set('editLimits.max_products', '4000')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $basic->refresh();
        $this->assertFalse((bool) $basic->modules()->where('code', 'pos')->first()?->pivot->enabled);
        $this->assertFalse((bool) ($basic->features()->where('code', 'pos.frozen_sales')->first()?->pivot?->enabled ?? false));
        $this->assertSame(4000, $basic->limits()->where('limit_key', 'max_products')->value('limit_value'));
    }

    public function test_disabling_a_module_clears_its_features_from_the_pending_edit_state(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platformAdmin);

        $basic = Plan::query()->where('code', 'basic')->firstOrFail();
        $posModule = Module::query()->where('code', 'pos')->firstOrFail();
        $mixedPaymentsFeature = Feature::query()->where('code', 'pos.mixed_payments')->firstOrFail();

        Livewire::test(PlansPage::class)
            ->call('startEdit', $basic->id)
            ->assertSet('editFeatureIds', fn (array $ids) => in_array($mixedPaymentsFeature->id, $ids, true))
            ->call('toggleModule', $posModule->id)
            ->assertSet('editFeatureIds', fn (array $ids) => ! in_array($mixedPaymentsFeature->id, $ids, true));
    }

    public function test_plan_edits_are_reflected_by_the_company_plan_resolver(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platformAdmin);

        $basic = Plan::query()->where('code', 'basic')->firstOrFail();

        Livewire::test(PlansPage::class)
            ->call('startEdit', $basic->id)
            ->set('editLimits.max_users', '77')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $company = \App\Models\Company::factory()->create();
        \App\Models\Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $basic->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $this->assertSame(77, app(CompanyPlanResolver::class)->limit($company, 'max_users'));
    }
}
