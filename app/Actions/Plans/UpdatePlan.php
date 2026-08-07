<?php

namespace App\Actions\Plans;

use App\Models\Feature;
use App\Models\Module;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Support\Plans\PlanLimitCatalog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdatePlan
{
    public function handle(Plan $plan, array $attributes): Plan
    {
        $moduleIds = array_map('intval', $attributes['module_ids'] ?? []);
        $featureIds = array_map('intval', $attributes['feature_ids'] ?? []);
        $limits = $attributes['limits'] ?? [];

        if (! in_array($attributes['billing_period'] ?? null, ['monthly', 'yearly', 'one_time'], true)) {
            throw new InvalidArgumentException('El periodo de facturacion no es valido.');
        }

        if (! in_array($attributes['status'] ?? null, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('El estado del plan no es valido.');
        }

        return DB::transaction(function () use ($plan, $attributes, $moduleIds, $featureIds, $limits) {
            $plan->update([
                'name' => $attributes['name'],
                'base_price' => $attributes['base_price'],
                'billing_period' => $attributes['billing_period'],
                'status' => $attributes['status'],
            ]);

            $moduleSync = Module::query()->pluck('id')
                ->mapWithKeys(fn (int $id) => [$id => ['enabled' => in_array($id, $moduleIds, true)]])
                ->all();
            $plan->modules()->sync($moduleSync);

            $featureSync = Feature::query()->pluck('id')
                ->mapWithKeys(fn (int $id) => [$id => ['enabled' => in_array($id, $featureIds, true)]])
                ->all();
            $plan->features()->sync($featureSync);

            foreach (PlanLimitCatalog::keys() as $key) {
                PlanLimit::query()->updateOrCreate(
                    ['plan_id' => $plan->id, 'limit_key' => $key],
                    ['limit_value' => (int) ($limits[$key] ?? 0)]
                );
            }

            return $plan->fresh(['modules', 'features', 'limits']);
        });
    }
}
