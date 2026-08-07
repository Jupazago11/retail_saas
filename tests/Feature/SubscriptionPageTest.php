<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Admin\SubscriptionPage;
use App\Models\RoleTemplate;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_page_can_replace_direct_subscription_for_current_company(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Suscripcion Demo SAS',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $proPlanId = \App\Models\Plan::query()->where('code', 'pro')->value('id');

        Livewire::test(SubscriptionPage::class)
            ->assertSee('Basic')
            ->set('selectedPlanId', (string) $proPlanId)
            ->set('subscriptionStatus', 'active')
            ->set('startsAt', now()->format('Y-m-d\TH:i'))
            ->call('saveSubscription')
            ->assertHasNoErrors()
            ->assertSee('Pro');

        $company->refresh();

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan_id' => $proPlanId,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'status' => 'ended',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'subscription.created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'subscription.ended',
        ]);

        $this->assertSame('pro', app(\App\Services\Plans\CompanyPlanResolver::class)->snapshot($company)['plan']?->code);
    }

    public function test_subscription_page_can_end_and_renew_direct_subscription(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Suscripcion Lifecycle SAS',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $initialSubscription = Subscription::query()
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->latest('id')
            ->firstOrFail();

        Livewire::test(SubscriptionPage::class)
            ->call('endSubscription', $initialSubscription->id)
            ->assertHasNoErrors();

        $initialSubscription->refresh();

        $this->assertSame('ended', $initialSubscription->status);
        $this->assertNotNull($initialSubscription->ends_at);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'subscription.ended',
            'auditable_id' => $initialSubscription->id,
        ]);

        Livewire::test(SubscriptionPage::class)
            ->call('renewSubscription', $initialSubscription->id)
            ->assertHasNoErrors()
            ->assertSee('Basic');

        $this->assertDatabaseCount('subscriptions', 2);
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan_id' => $initialSubscription->plan_id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'subscription.created',
        ]);
    }

    public function test_subscription_page_route_is_forbidden_without_settings_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Suscripcion Restringida SAS',
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
            ->get(route('admin.subscription'))
            ->assertForbidden();
    }
}
