<?php

namespace App\Services\Plans;

use App\Enums\RecordStatus;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Plan;
use App\Support\Plans\PlanCatalog;
use Illuminate\Support\Facades\DB;

class PlanCatalogBootstrapper
{
    public function ensureDefaults(): void
    {
        DB::transaction(function () {
            $now = now();

            Module::query()->upsert(
                collect(PlanCatalog::modules())
                    ->map(fn (array $module) => [
                        ...$module,
                        'status' => RecordStatus::Active->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all(),
                ['code'],
                ['name', 'status', 'updated_at']
            );

            $moduleIds = Module::query()
                ->pluck('id', 'code')
                ->all();

            Feature::query()->upsert(
                collect(PlanCatalog::features())
                    ->map(fn (array $feature) => [
                        'module_id' => $moduleIds[$feature['module_code']],
                        'code' => $feature['code'],
                        'name' => $feature['name'],
                        'status' => RecordStatus::Active->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all(),
                ['code'],
                ['module_id', 'name', 'status', 'updated_at']
            );

            // insertOrIgnore (not upsert): once a plan row exists, its name/price/period/status
            // belong to the database (editable from Plataforma > Planes) and must survive
            // future calls to ensureDefaults() triggered by normal traffic (company creation,
            // subscription changes).
            Plan::query()->insertOrIgnore(
                collect(PlanCatalog::plans())
                    ->map(fn (array $plan) => [
                        'code' => $plan['code'],
                        'name' => $plan['name'],
                        'status' => RecordStatus::Active->value,
                        'billing_period' => $plan['billing_period'],
                        'base_price' => $plan['base_price'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all()
            );

            $planIds = Plan::query()
                ->pluck('id', 'code')
                ->all();

            $featureIds = Feature::query()
                ->pluck('id', 'code')
                ->all();

            // insertOrIgnore below: a plan's module/feature enablement and limit values become
            // database-owned as soon as a row exists (editable from Plataforma > Planes). Only
            // missing rows (a brand new plan, or a catalog entry added after the fact) get
            // seeded from PlanCatalog.php; existing rows are never forced back to the hardcoded
            // defaults.
            DB::table('plan_modules')->insertOrIgnore(
                collect(PlanCatalog::plans())
                    ->flatMap(function (array $plan) use ($planIds, $moduleIds, $now) {
                        return collect($plan['modules'])
                            ->map(fn (string $moduleCode) => [
                                'plan_id' => $planIds[$plan['code']],
                                'module_id' => $moduleIds[$moduleCode],
                                'enabled' => true,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ])
                            ->all();
                    })
                    ->all()
            );

            DB::table('plan_features')->insertOrIgnore(
                collect(PlanCatalog::plans())
                    ->flatMap(function (array $plan) use ($planIds, $featureIds, $now) {
                        return collect($plan['features'])
                            ->map(fn (string $featureCode) => [
                                'plan_id' => $planIds[$plan['code']],
                                'feature_id' => $featureIds[$featureCode],
                                'enabled' => true,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ])
                            ->all();
                    })
                    ->all()
            );

            DB::table('plan_limits')->insertOrIgnore(
                collect(PlanCatalog::plans())
                    ->flatMap(function (array $plan) use ($planIds, $now) {
                        return collect($plan['limits'])
                            ->map(fn (int $value, string $key) => [
                                'plan_id' => $planIds[$plan['code']],
                                'limit_key' => $key,
                                'limit_value' => $value,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ])
                            ->values()
                            ->all();
                    })
                    ->all()
            );
        });
    }
}
