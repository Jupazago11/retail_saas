<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Subscriptions\ChangeCompanySubscription;
use App\Actions\Subscriptions\ProcessDueSubscriptions;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessDueSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renews_expired_subscription_when_company_has_auto_renew_enabled(): void
    {
        $company = $this->createCompanyWithExpiredSubscription(autoRenew: true);

        $processed = app(ProcessDueSubscriptions::class)->handle();

        $this->assertSame(1, $processed);

        $active = Subscription::query()
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->where('status', 'active')
            ->get();

        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->ends_at->isFuture());

        $ended = Subscription::query()
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->where('status', 'ended')
            ->get();

        $this->assertGreaterThanOrEqual(1, $ended->count());
    }

    public function test_it_only_ends_expired_subscription_when_auto_renew_is_disabled(): void
    {
        $company = $this->createCompanyWithExpiredSubscription(autoRenew: false);

        $processed = app(ProcessDueSubscriptions::class)->handle();

        $this->assertSame(1, $processed);

        $this->assertSame(0, Subscription::query()
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->where('status', 'active')
            ->count());
    }

    public function test_it_ignores_subscriptions_without_end_date(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Sin Vencimiento SAS']);
        $company->update(['auto_renew' => true]);

        $basicPlanId = Plan::query()->where('code', 'basic')->value('id');

        app(ChangeCompanySubscription::class)->handle($company, [
            'plan_id' => $basicPlanId,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
        ]);

        $processed = app(ProcessDueSubscriptions::class)->handle();

        $this->assertSame(0, $processed);
    }

    protected function createCompanyWithExpiredSubscription(bool $autoRenew): \App\Models\Company
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Expirada '.$owner->id.' SAS',
        ]);
        $company->update(['auto_renew' => $autoRenew]);

        $basicPlanId = Plan::query()->where('code', 'basic')->value('id');

        $subscription = app(ChangeCompanySubscription::class)->handle($company, [
            'plan_id' => $basicPlanId,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
        ]);
        $subscription->update(['ends_at' => now()->subDay()]);

        return $company;
    }
}
