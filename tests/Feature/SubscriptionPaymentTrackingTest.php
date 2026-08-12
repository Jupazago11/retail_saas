<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Livewire\Platform\SubscriptionsPage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionPaymentTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_a_plan_marks_it_as_paid_with_the_reference(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Pago Demo SAS']);

        $proPlanId = Plan::query()->where('code', 'pro')->value('id');

        $this->actingAs($platformAdmin);

        Livewire::test(SubscriptionsPage::class)
            ->call('startActivate', $company->id)
            ->set('activatePlanId', (string) $proPlanId)
            ->set('activatePaymentReference', 'Transferencia Bancolombia #12345')
            ->call('saveActivate')
            ->assertHasNoErrors();

        $subscription = Subscription::query()
            ->where('company_id', $company->id)
            ->where('plan_id', $proPlanId)
            ->firstOrFail();

        $this->assertTrue($subscription->isPaid());
        $this->assertNotNull($subscription->paid_at);
        $this->assertSame('Transferencia Bancolombia #12345', $subscription->payment_reference);
    }

    public function test_a_subscription_without_paid_at_can_be_marked_paid_retroactively(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Auto Renovada SAS']);

        // Simula una suscripcion que se auto-reno vo sola (auto_renew) y que
        // nadie ha confirmado el pago todavia.
        $subscription = Subscription::query()
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->latest('id')
            ->firstOrFail();

        $this->assertFalse($subscription->fresh()->isPaid());

        $this->actingAs($platformAdmin);

        Livewire::test(SubscriptionsPage::class)
            ->call('markPaid', $subscription->id)
            ->assertHasNoErrors();

        $this->assertTrue($subscription->fresh()->isPaid());
    }
}
