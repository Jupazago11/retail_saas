<?php

namespace App\Actions\Plans;

use App\Models\Company;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Services\Settings\CompanySettings;
use App\Support\Plans\PlanLimitCatalog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdatePlan
{
    public function __construct(
        protected CompanySettings $companySettings,
    ) {}

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

        $creditWasEnabled = $this->planHasCreditEnabled($plan);

        return DB::transaction(function () use ($plan, $attributes, $moduleIds, $featureIds, $limits, $creditWasEnabled) {
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

            $plan = $plan->fresh(['modules', 'features', 'limits']);

            // Solo en la transicion apagado->encendido: si el credito del
            // plan ya estaba activo, una empresa pudo haber apagado el
            // interruptor operativo (Configuracion > Credito) a proposito;
            // guardar el plan de nuevo sin cambiar este estado no debe
            // reencenderlo por ella.
            if (! $creditWasEnabled && $this->planHasCreditEnabled($plan)) {
                $this->enableCreditForCompaniesOnPlan($plan);
            }

            return $plan;
        });
    }

    protected function planHasCreditEnabled(Plan $plan): bool
    {
        return $plan->modules()->wherePivot('enabled', true)->where('modules.code', 'credit')->exists()
            && $plan->features()->wherePivot('enabled', true)->where('features.code', 'credit.enabled')->exists();
    }

    // El modulo/feature de un plan solo dicen a que tiene derecho una
    // empresa; "Credito habilitado" (company_settings) es el interruptor
    // operativo real que consume el POS. Sin este cascade, una empresa
    // suscrita a un plan que ya trae credito seguia arrancando con ese
    // interruptor apagado (default de CompanySettingCatalog) hasta que
    // alguien entrara manualmente a Configuracion a prenderlo.
    protected function enableCreditForCompaniesOnPlan(Plan $plan): void
    {
        Subscription::query()
            ->where('plan_id', $plan->id)
            ->whereNull('bundle_id')
            ->activeAt(now())
            ->with('company')
            ->get()
            ->pluck('company')
            ->filter()
            ->unique('id')
            ->each(fn (Company $company) => $this->companySettings->set($company, 'credit', 'credit_enabled', true));
    }
}
