<?php

namespace App\Services\Plans;

use App\Models\Company;
use App\Models\CompanyFeatureOverride;
use App\Models\CompanyLimitOverride;
use App\Models\CompanyModuleOverride;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionBundleCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CompanyPlanResolver
{
    public function snapshot(Company $company, ?Carbon $moment = null): array
    {
        $moment ??= now();

        [$subscription, $plan, $source] = $this->resolveContext($company, $moment);

        if (! $subscription || ! $plan) {
            return [
                'subscription' => null,
                'plan' => null,
                'source' => 'none',
                'modules' => [],
                'features' => [],
                'limits' => [],
            ];
        }

        $modules = $plan->modules()
            ->wherePivot('enabled', true)
            ->get(['modules.code'])
            ->pluck('code')
            ->mapWithKeys(fn (string $code) => [$code => true])
            ->all();

        $features = $plan->features()
            ->wherePivot('enabled', true)
            ->get(['features.code'])
            ->pluck('code')
            ->mapWithKeys(fn (string $code) => [$code => true])
            ->all();

        $limits = $plan->limits()
            ->get(['limit_key', 'limit_value'])
            ->mapWithKeys(fn ($limit) => [$limit->limit_key => (int) $limit->limit_value])
            ->all();

        $this->applyModuleOverrides($company, $modules, $moment);
        $this->applyFeatureOverrides($company, $features, $moment);
        $this->applyLimitOverrides($company, $limits, $moment);

        return [
            'subscription' => $subscription,
            'plan' => $plan,
            'source' => $source,
            'modules' => $modules,
            'features' => $features,
            'limits' => $limits,
        ];
    }

    public function hasModule(Company $company, string $moduleCode, ?Carbon $moment = null): bool
    {
        return (bool) ($this->snapshot($company, $moment)['modules'][$moduleCode] ?? false);
    }

    public function hasFeature(Company $company, string $featureCode, ?Carbon $moment = null): bool
    {
        return (bool) ($this->snapshot($company, $moment)['features'][$featureCode] ?? false);
    }

    public function limit(Company $company, string $limitKey, ?Carbon $moment = null): ?int
    {
        return $this->snapshot($company, $moment)['limits'][$limitKey] ?? null;
    }

    protected function resolveContext(Company $company, Carbon $moment): array
    {
        $directSubscription = Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->whereNull('bundle_id')
            ->activeAt($moment)
            ->latest('starts_at')
            ->latest('id')
            ->first();

        if ($directSubscription && $directSubscription->plan) {
            return [$directSubscription, $directSubscription->plan, 'direct'];
        }

        $bundleMembership = SubscriptionBundleCompany::query()
            ->with([
                'plan',
                'bundle',
                'bundle.subscriptions' => fn ($query) => $query->activeAt($moment)->latest('starts_at')->latest('id'),
            ])
            ->where('company_id', $company->id)
            ->whereHas('bundle.subscriptions', fn (Builder $query) => $query->activeAt($moment))
            ->latest('id')
            ->first();

        if (! $bundleMembership || ! $bundleMembership->bundle) {
            return [null, null, 'none'];
        }

        $bundleSubscription = $bundleMembership->bundle->subscriptions->first();
        $plan = $bundleMembership->plan ?? $bundleSubscription?->plan;

        if (! $bundleSubscription || ! $plan) {
            return [null, null, 'none'];
        }

        return [$bundleSubscription, $plan, 'bundle'];
    }

    protected function applyModuleOverrides(Company $company, array &$modules, Carbon $moment): void
    {
        $this->activeOverrides(
            CompanyModuleOverride::query()
                ->with('module:id,code')
                ->where('company_id', $company->id),
            $moment
        )
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->each(function (CompanyModuleOverride $override) use (&$modules) {
                if ($override->module) {
                    $modules[$override->module->code] = $override->enabled;
                }
            });
    }

    protected function applyFeatureOverrides(Company $company, array &$features, Carbon $moment): void
    {
        $this->activeOverrides(
            CompanyFeatureOverride::query()
                ->with('feature:id,code')
                ->where('company_id', $company->id),
            $moment
        )
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->each(function (CompanyFeatureOverride $override) use (&$features) {
                if ($override->feature) {
                    $features[$override->feature->code] = $override->enabled;
                }
            });
    }

    protected function applyLimitOverrides(Company $company, array &$limits, Carbon $moment): void
    {
        $this->activeOverrides(
            CompanyLimitOverride::query()
                ->where('company_id', $company->id),
            $moment
        )
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->each(function (CompanyLimitOverride $override) use (&$limits) {
                $limits[$override->limit_key] = (int) $override->limit_value;
            });
    }

    protected function activeOverrides(Builder $query, Carbon $moment): Builder
    {
        return $query
            ->where(function (Builder $startsQuery) use ($moment) {
                $startsQuery
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $moment);
            })
            ->where(function (Builder $endsQuery) use ($moment) {
                $endsQuery
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $moment);
            });
    }
}
