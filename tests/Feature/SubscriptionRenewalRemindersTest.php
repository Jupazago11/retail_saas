<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Subscriptions\ChangeCompanySubscription;
use App\Actions\Subscriptions\SendSubscriptionRenewalReminders;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Mail\SubscriptionsDueTodayMail;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionRenewalRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_emails_the_company_owner_and_the_platform_admin_when_a_subscription_is_due_today(): void
    {
        Mail::fake();
        PlatformSetting::set('owner_notification_email', 'admin@retailsaas.test');

        $owner = User::factory()->create(['email' => 'duenio@retailsaas.test']);
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Vence Hoy SAS']);

        $planId = Plan::query()->where('code', 'basic')->value('id');
        app(ChangeCompanySubscription::class)->handle($company, [
            'plan_id' => $planId,
            'status' => 'active',
            'starts_at' => now()->subDays(31),
            'ends_at' => now(),
        ]);

        $otherOwner = User::factory()->create(['email' => 'otro@retailsaas.test']);
        $otherCompany = app(CreateCompany::class)->handle($otherOwner, ['legal_name' => 'No Vence SAS']);
        app(ChangeCompanySubscription::class)->handle($otherCompany, [
            'plan_id' => $planId,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(31),
        ]);

        $count = app(SendSubscriptionRenewalReminders::class)->handle();

        $this->assertSame(1, $count);

        Mail::assertSent(SubscriptionRenewalReminderMail::class, fn ($mail) => $mail->hasTo('duenio@retailsaas.test')
            && $mail->subscription->company->is($company));

        Mail::assertNotSent(SubscriptionRenewalReminderMail::class, fn ($mail) => $mail->hasTo('otro@retailsaas.test'));

        Mail::assertSent(SubscriptionsDueTodayMail::class, fn ($mail) => $mail->hasTo('admin@retailsaas.test')
            && $mail->subscriptions->count() === 1
            && $mail->subscriptions->first()->company->is($company));
    }

    public function test_it_sends_nothing_when_no_subscription_is_due_today(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Al Dia SAS']);

        $planId = Plan::query()->where('code', 'basic')->value('id');
        app(ChangeCompanySubscription::class)->handle($company, [
            'plan_id' => $planId,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(31),
        ]);

        $count = app(SendSubscriptionRenewalReminders::class)->handle();

        $this->assertSame(0, $count);
        Mail::assertNothingSent();
    }
}
