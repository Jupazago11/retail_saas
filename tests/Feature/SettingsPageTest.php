<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Admin\SettingsPage;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_settings_page_can_persist_company_settings_and_sync_company_profile(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Configuracion UI SAS',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SettingsPage::class)
            ->set('settings.general.display_name', 'Retail Central')
            ->set('settings.general.tax_id', '900123456')
            ->set('settings.pos.allow_manual_discounts', true)
            ->set('settings.cash.default_opening_amount', '125000.50')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $company->refresh();

        $this->assertSame('Configuracion UI SAS', $company->legal_name);
        $this->assertSame('Retail Central', $company->display_name);
        $this->assertSame('900123456', $company->tax_id);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'group_key' => 'pos',
            'setting_key' => 'allow_manual_discounts',
            'value_boolean' => true,
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'group_key' => 'cash',
            'setting_key' => 'default_opening_amount',
            'value_decimal' => '125000.5000',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'company_setting.created',
        ]);
    }

    public function test_settings_page_route_is_forbidden_without_settings_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Configuracion Restringida SAS',
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
            ->get(route('admin.settings'))
            ->assertForbidden();
    }

    public function test_settings_page_can_persist_electronic_billing_settings_when_plan_supports_it(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Facturacion Premium SAS',
        ]);
        $this->assignCompanyPlan($company, 'premium');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SettingsPage::class)
            ->set('settings.electronic_billing.enabled', true)
            ->set('settings.electronic_billing.provider', 'factus')
            ->set('settings.electronic_billing.environment', 'production')
            ->set('settings.electronic_billing.prefix', 'FE')
            ->set('settings.electronic_billing.sequence_current', 15)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'group_key' => 'electronic_billing',
            'setting_key' => 'enabled',
            'value_boolean' => true,
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'group_key' => 'electronic_billing',
            'setting_key' => 'environment',
            'value_string' => 'production',
        ]);
    }

    public function test_settings_page_rejects_electronic_billing_settings_when_plan_lacks_module(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Facturacion Basic SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SettingsPage::class)
            ->set('settings.electronic_billing.enabled', true)
            ->call('saveSettings')
            ->assertHasErrors(['settings']);
    }
}
