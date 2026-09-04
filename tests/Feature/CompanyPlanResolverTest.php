<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\CompanyFeatureOverride;
use App\Models\CompanyLimitOverride;
use App\Models\CompanyModuleOverride;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionBundle;
use App\Models\SubscriptionBundleCompany;
use App\Models\User;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Plans\PlanCatalogBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class CompanyPlanResolverTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_company_creation_provisions_basic_trial_subscription_and_catalog(): void
    {
        $owner = User::factory()->create();

        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Plan Resolver SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');

        $resolver = app(CompanyPlanResolver::class);
        $snapshot = $resolver->snapshot($company);

        $this->assertDatabaseHas('plans', ['code' => 'basic']);
        $this->assertDatabaseHas('modules', ['code' => 'pos']);
        $this->assertDatabaseHas('features', ['code' => 'pos.mixed_payments']);
        $this->assertSame('direct', $snapshot['source']);
        $this->assertSame('basic', $snapshot['plan']?->code);
        $this->assertTrue($resolver->hasModule($company, 'products'));
        $this->assertTrue($resolver->hasFeature($company, 'pos.mixed_payments'));
        $this->assertFalse($resolver->hasFeature($company, 'pos.frozen_sales'));
        $this->assertSame(3, $resolver->limit($company, 'max_users'));
    }

    public function test_company_overrides_replace_base_modules_features_and_limits(): void
    {
        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        $company = Company::factory()->create();
        $plan = Plan::query()->where('code', 'basic')->firstOrFail();

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        CompanyModuleOverride::query()->create([
            'company_id' => $company->id,
            'module_id' => Module::query()->where('code', 'loyalty')->value('id'),
            'enabled' => true,
            'starts_at' => now()->subHour(),
        ]);

        CompanyFeatureOverride::query()->create([
            'company_id' => $company->id,
            'feature_id' => Feature::query()->where('code', 'pos.frozen_sales')->value('id'),
            'enabled' => true,
            'starts_at' => now()->subHour(),
        ]);

        CompanyLimitOverride::query()->create([
            'company_id' => $company->id,
            'limit_key' => 'max_users',
            'limit_value' => 25,
            'starts_at' => now()->subHour(),
        ]);

        $resolver = app(CompanyPlanResolver::class);

        $this->assertTrue($resolver->hasModule($company, 'loyalty'));
        $this->assertTrue($resolver->hasFeature($company, 'pos.frozen_sales'));
        $this->assertSame(25, $resolver->limit($company, 'max_users'));
    }

    public function test_bundle_assignment_can_resolve_effective_plan_when_company_has_no_direct_subscription(): void
    {
        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        $owner = User::factory()->create();
        $company = Company::factory()->create();
        $premiumPlan = Plan::query()->where('code', 'premium')->firstOrFail();

        $bundle = SubscriptionBundle::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Grupo Premium',
            'status' => RecordStatus::Active->value,
            'max_companies' => 3,
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
        ]);

        Subscription::query()->create([
            'bundle_id' => $bundle->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        SubscriptionBundleCompany::query()->create([
            'bundle_id' => $bundle->id,
            'company_id' => $company->id,
            'plan_id' => $premiumPlan->id,
        ]);

        $resolver = app(CompanyPlanResolver::class);
        $snapshot = $resolver->snapshot($company);

        $this->assertSame('bundle', $snapshot['source']);
        $this->assertSame('premium', $snapshot['plan']?->code);
        $this->assertTrue($resolver->hasModule($company, 'electronic_billing'));
        $this->assertTrue($resolver->hasFeature($company, 'pos.combos'));
        $this->assertSame(3, $resolver->limit($company, 'max_companies'));
    }
}
