<?php

namespace Tests\Feature;

use App\Livewire\Platform\PlansPage;
use App\Models\Company;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Plans\PlanCatalogBootstrapper;
use App\Services\Settings\CompanySettings;
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

        $company = Company::factory()->create();
        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $basic->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $this->assertSame(77, app(CompanyPlanResolver::class)->limit($company, 'max_users'));
    }

    public function test_enabling_credit_on_a_plan_turns_it_on_for_subscribed_companies(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platformAdmin);

        $basic = Plan::query()->where('code', 'basic')->firstOrFail();
        $creditModule = Module::query()->where('code', 'credit')->firstOrFail();
        $creditFeature = Feature::query()->where('code', 'credit.enabled')->firstOrFail();

        $company = Company::factory()->create();
        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $basic->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $this->assertFalse((bool) app(CompanySettings::class)->get($company, 'credit', 'credit_enabled'));

        Livewire::test(PlansPage::class)
            ->call('startEdit', $basic->id)
            ->call('toggleModule', $creditModule->id)
            ->call('toggleFeature', $creditFeature->id)
            ->call('saveEdit')
            ->assertHasNoErrors();

        $this->assertTrue((bool) app(CompanySettings::class)->get($company, 'credit', 'credit_enabled'));
    }

    public function test_resaving_a_plan_does_not_reenable_credit_for_a_company_that_turned_it_off(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platformAdmin);

        $basic = Plan::query()->where('code', 'basic')->firstOrFail();
        $creditModule = Module::query()->where('code', 'credit')->firstOrFail();
        $creditFeature = Feature::query()->where('code', 'credit.enabled')->firstOrFail();

        $company = Company::factory()->create();
        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $basic->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        Livewire::test(PlansPage::class)
            ->call('startEdit', $basic->id)
            ->call('toggleModule', $creditModule->id)
            ->call('toggleFeature', $creditFeature->id)
            ->call('saveEdit')
            ->assertHasNoErrors();

        // La empresa decide apagarlo manualmente despues del cascade inicial.
        app(CompanySettings::class)->set($company, 'credit', 'credit_enabled', false);

        // Guardar el plan de nuevo sin tocar credito (sigue prendido en el
        // plan) no debe reencenderlo por la empresa.
        Livewire::test(PlansPage::class)
            ->call('startEdit', $basic->id)
            ->set('editLimits.max_users', '15')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $this->assertFalse((bool) app(CompanySettings::class)->get($company, 'credit', 'credit_enabled'));
    }
}
