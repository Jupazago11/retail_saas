<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCompaniesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_three_demo_companies_with_different_active_plans(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('companies', 3);

        $this->assertDemoCompanyPlan('demo.basic@retailsaas.test', 'Demo Basic Market SAS', 'basic');
        $this->assertDemoCompanyPlan('demo.pro@retailsaas.test', 'Demo Pro Retail SAS', 'pro');
        $this->assertDemoCompanyPlan('demo.premium@retailsaas.test', 'Demo Premium Commerce SAS', 'premium');
    }

    public function test_database_seeder_is_idempotent_for_demo_companies(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('companies', 3);
        $this->assertSame(6, Subscription::query()->count());

        $activeDirectSubscriptions = Subscription::query()
            ->whereNull('bundle_id')
            ->activeAt(now())
            ->count();

        $this->assertSame(3, $activeDirectSubscriptions);
    }

    public function test_database_seeder_preserves_remember_token_for_existing_demo_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'demo.basic@retailsaas.test')->firstOrFail();
        $originalToken = $user->remember_token;

        $this->seed(DatabaseSeeder::class);

        $user->refresh();

        $this->assertSame($originalToken, $user->remember_token);
    }

    protected function assertDemoCompanyPlan(string $email, string $legalName, string $planCode): void
    {
        $owner = User::query()->where('email', $email)->firstOrFail();
        $company = Company::query()
            ->where('owner_user_id', $owner->id)
            ->where('legal_name', $legalName)
            ->firstOrFail();

        $activeDirectSubscription = Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->activeAt(now())
            ->latest('starts_at')
            ->latest('id')
            ->first();

        $this->assertNotNull($activeDirectSubscription);
        $this->assertSame('active', $activeDirectSubscription->status);
        $this->assertSame($planCode, $activeDirectSubscription->plan?->code);
    }
}
