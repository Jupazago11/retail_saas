<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Subscriptions\ChangeCompanySubscription;
use App\Livewire\Platform\SubscriptionsPage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformSubscriptionsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_subscription_is_shown_as_vencida_even_if_status_is_active(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Vencida SAS']);

        $basicPlanId = Plan::query()->where('code', 'basic')->value('id');
        $subscription = app(ChangeCompanySubscription::class)->handle($company, [
            'plan_id' => $basicPlanId,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
        ]);
        $subscription->update(['ends_at' => now()->subDay()]);

        $this->actingAs($platformAdmin);

        Livewire::test(SubscriptionsPage::class)
            ->assertSee('vencida')
            ->assertDontSee('activa');
    }

    public function test_platform_admin_can_toggle_auto_renew_for_a_company(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Toggle SAS']);

        // Las empresas nuevas quedan con auto-renovacion activada por defecto.
        $this->assertTrue($company->fresh()->auto_renew);

        $this->actingAs($platformAdmin);

        Livewire::test(SubscriptionsPage::class)
            ->call('toggleAutoRenew', $company->id)
            ->assertHasNoErrors();

        $this->assertFalse($company->fresh()->auto_renew);
    }

    public function test_non_platform_admin_cannot_access_the_page(): void
    {
        $viewer = User::factory()->create(['is_platform_admin' => false]);

        $this->actingAs($viewer);

        Livewire::test(SubscriptionsPage::class)->assertStatus(403);
    }

    public function test_platform_admin_can_activate_a_new_plan_for_an_expired_company(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Activar SAS']);

        $basicPlanId = Plan::query()->where('code', 'basic')->value('id');
        $subscription = app(ChangeCompanySubscription::class)->handle($company, [
            'plan_id' => $basicPlanId,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
        ]);
        $subscription->update(['ends_at' => now()->subDay()]);

        $proPlanId = Plan::query()->where('code', 'pro')->value('id');

        $this->actingAs($platformAdmin);

        Livewire::test(SubscriptionsPage::class)
            ->call('startActivate', $company->id)
            ->set('activatePlanId', (string) $proPlanId)
            ->set('activateStartsAt', now()->format('Y-m-d'))
            ->call('saveActivate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan_id' => $proPlanId,
            'status' => 'active',
        ]);

        $this->assertSame(1, Subscription::query()
            ->where('company_id', $company->id)
            ->where('plan_id', $proPlanId)
            ->where('status', 'active')
            ->count());
    }
}
