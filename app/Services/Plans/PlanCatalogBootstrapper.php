<?php

namespace App\Services\Plans;

use App\Enums\RecordStatus;
use App\Models\BusinessType;
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

            $businessTypeIds = BusinessType::query()->pluck('id', 'code')->all();

            Module::query()->upsert(
                collect(PlanCatalog::modules())
                    ->map(fn (array $module) => [
                        'code' => $module['code'],
                        'name' => $module['name'],
                        'business_type_id' => $module['business_type_code'] ? $businessTypeIds[$module['business_type_code']] : null,
                        'status' => RecordStatus::Active->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all(),
                ['code'],
                ['name', 'business_type_id', 'status', 'updated_at']
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
                        'business_type_id' => $businessTypeIds[$plan['business_type_code']],
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

            // Clave compuesta business_type_id:code — "code" solo por si solo ya no
            // identifica un plan de forma unica desde que plans.code se volvio
            // unico por vertical (unique compuesto business_type_id+code). Usar
            // solo "code" aqui perdia silenciosamente uno de los dos planes
            // "basic" (general y restaurant) al mapear id por code.
            $planIds = Plan::query()
                ->get(['id', 'code', 'business_type_id'])
                ->mapWithKeys(fn (Plan $plan) => ["{$plan->business_type_id}:{$plan->code}" => $plan->id])
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
                    ->flatMap(function (array $plan) use ($planIds, $moduleIds, $businessTypeIds, $now) {
                        $planId = $planIds["{$businessTypeIds[$plan['business_type_code']]}:{$plan['code']}"];

                        return collect($plan['modules'])
                            ->map(fn (string $moduleCode) => [
                                'plan_id' => $planId,
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
                    ->flatMap(function (array $plan) use ($planIds, $featureIds, $businessTypeIds, $now) {
                        $planId = $planIds["{$businessTypeIds[$plan['business_type_code']]}:{$plan['code']}"];

                        return collect($plan['features'])
                            ->map(fn (string $featureCode) => [
                                'plan_id' => $planId,
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
                    ->flatMap(function (array $plan) use ($planIds, $businessTypeIds, $now) {
                        $planId = $planIds["{$businessTypeIds[$plan['business_type_code']]}:{$plan['code']}"];

                        return collect($plan['limits'])
                            ->map(fn (int $value, string $key) => [
                                'plan_id' => $planId,
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
