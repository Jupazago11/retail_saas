<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Admin\PlanOverridesPage;
use App\Models\User;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanOverridesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_overrides_page_can_create_and_update_module_feature_and_limit_overrides(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Overrides Demo SAS',
        ]);

        $moduleId = \App\Models\Module::query()->where('code', 'loyalty')->value('id');
        $featureId = \App\Models\Feature::query()->where('code', 'pos.frozen_sales')->value('id');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PlanOverridesPage::class)
            ->set('moduleId', (string) $moduleId)
            ->set('moduleEnabled', true)
            ->call('saveModuleOverride')
            ->assertHasNoErrors()
            ->set('featureId', (string) $featureId)
            ->set('featureEnabled', true)
            ->call('saveFeatureOverride')
            ->assertHasNoErrors()
            ->set('limitKey', 'max_users')
            ->set('limitValue', '25')
            ->call('saveLimitOverride')
            ->assertHasNoErrors();

        $resolver = app(CompanyPlanResolver::class);

        $this->assertTrue($resolver->hasModule($company, 'loyalty'));
        $this->assertTrue($resolver->hasFeature($company, 'pos.frozen_sales'));
        $this->assertSame(25, $resolver->limit($company, 'max_users'));

        $moduleOverride = \App\Models\CompanyModuleOverride::query()->where('company_id', $company->id)->firstOrFail();
        $featureOverride = \App\Models\CompanyFeatureOverride::query()->where('company_id', $company->id)->firstOrFail();
        $limitOverride = \App\Models\CompanyLimitOverride::query()->where('company_id', $company->id)->firstOrFail();

        Livewire::test(PlanOverridesPage::class)
            ->call('startEditingModuleOverride', $moduleOverride->id)
            ->set('moduleEnabled', false)
            ->call('saveModuleOverride')
            ->assertHasNoErrors()
            ->call('startEditingFeatureOverride', $featureOverride->id)
            ->set('featureEnabled', false)
            ->call('saveFeatureOverride')
            ->assertHasNoErrors()
            ->call('startEditingLimitOverride', $limitOverride->id)
            ->set('limitValue', '9')
            ->call('saveLimitOverride')
            ->assertHasNoErrors();

        $this->assertFalse($resolver->hasModule($company->fresh(), 'loyalty'));
        $this->assertFalse($resolver->hasFeature($company->fresh(), 'pos.frozen_sales'));
        $this->assertSame(9, $resolver->limit($company->fresh(), 'max_users'));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'company_module_override.created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'company_module_override.updated',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'company_feature_override.created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'company_feature_override.updated',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'company_limit_override.created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'company_limit_override.updated',
        ]);
    }

    public function test_plan_overrides_page_route_is_forbidden_without_settings_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Overrides Restringido SAS',
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
            ->get(route('admin.overrides'))
            ->assertForbidden();
    }
}
