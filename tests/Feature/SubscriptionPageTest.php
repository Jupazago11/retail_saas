<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Admin\SubscriptionPage;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SubscriptionPageTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_subscription_page_shows_current_plan_and_other_plans_as_a_carousel(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Suscripcion Demo SAS',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SubscriptionPage::class)
            ->assertSee('Tu plan actual')
            ->assertSee('Basic')
            ->assertSee('Planes disponibles')
            ->assertSee('Pro')
            ->assertSee('Premium')
            ->assertDontSee('Guardar suscripcion')
            ->assertDontSee('Historial directo');
    }

    public function test_subscription_page_shows_the_whatsapp_link_for_payment_proof(): void
    {
        PlatformSetting::set('contact_phone', '3135721225');

        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Suscripcion WhatsApp SAS',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(SubscriptionPage::class)
            ->assertSee('573135721225')
            ->assertSee('WhatsApp');
    }

    public function test_subscription_page_route_is_forbidden_without_settings_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Suscripcion Restringida SAS',
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
            ->get(route('admin.subscription'))
            ->assertForbidden();
    }
}
