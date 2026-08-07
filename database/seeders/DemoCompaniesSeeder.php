<?php

namespace Database\Seeders;

use App\Actions\Companies\CreateCompany;
use App\Actions\Subscriptions\ChangeCompanySubscription;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Authorization\AuthorizationCatalogBootstrapper;
use App\Services\Plans\PlanCatalogBootstrapper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoCompaniesSeeder extends Seeder
{
    public function run(): void
    {
        app(AuthorizationCatalogBootstrapper::class)->ensureDefaults();
        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        collect($this->demoProfiles())->each(function (array $profile) {
            $owner = User::query()->firstOrNew(['email' => $profile['email']]);
            $owner->fill([
                'name' => $profile['name'],
                'username' => $profile['username'],
                'password' => Hash::make($profile['password']),
                'status' => 'active',
                'email_verified_at' => $owner->email_verified_at ?? now(),
            ]);

            if (blank($owner->remember_token)) {
                $owner->remember_token = Str::random(10);
            }

            $owner->save();

            $company = $owner->ownedCompanies()
                ->where('legal_name', $profile['company']['legal_name'])
                ->first();

            if (! $company) {
                $company = app(CreateCompany::class)->handle($owner, $profile['company']);
            }

            $this->ensureDirectPlan($company, $profile['plan_code'], $owner);
        });
    }

    protected function ensureDirectPlan(Company $company, string $planCode, User $owner): void
    {
        $plan = Plan::query()->where('code', $planCode)->firstOrFail();

        $activeDirectSubscription = Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->activeAt(now())
            ->latest('starts_at')
            ->latest('id')
            ->first();

        if (
            $activeDirectSubscription
            && (int) $activeDirectSubscription->plan_id === (int) $plan->id
            && $activeDirectSubscription->status === 'active'
        ) {
            return;
        }

        app(ChangeCompanySubscription::class)->handle($company, [
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ], $owner);
    }

    protected function demoProfiles(): array
    {
        return [
            [
                'name' => 'Demo Basic',
                'username' => 'demo.basic',
                'email' => 'demo.basic@retailsaas.test',
                'password' => 'password',
                'plan_code' => 'basic',
                'company' => [
                    'legal_name' => 'Demo Basic Market SAS',
                    'display_name' => 'Demo Basic Market',
                    'tax_id' => '900100001-1',
                ],
            ],
            [
                'name' => 'Demo Pro',
                'username' => 'demo.pro',
                'email' => 'demo.pro@retailsaas.test',
                'password' => 'password',
                'plan_code' => 'pro',
                'company' => [
                    'legal_name' => 'Demo Pro Retail SAS',
                    'display_name' => 'Demo Pro Retail',
                    'tax_id' => '900100002-2',
                ],
            ],
            [
                'name' => 'Demo Premium',
                'username' => 'demo.premium',
                'email' => 'demo.premium@retailsaas.test',
                'password' => 'password',
                'plan_code' => 'premium',
                'company' => [
                    'legal_name' => 'Demo Premium Commerce SAS',
                    'display_name' => 'Demo Premium Commerce',
                    'tax_id' => '900100003-3',
                ],
            ],
        ];
    }
}
