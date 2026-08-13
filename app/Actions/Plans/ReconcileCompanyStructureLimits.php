<?php

namespace App\Actions\Plans;

use App\Enums\RecordStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\CompanyPlanResolver;
use Illuminate\Database\Eloquent\Model;

class ReconcileCompanyStructureLimits
{
    public function __construct(
        protected CompanyPlanResolver $companyPlanResolver,
        protected AuditLogger $auditLogger,
    ) {
    }

    /**
     * Si el plan efectivo de la empresa bajo (por cambio de plan o de
     * override) por debajo de la cantidad de sucursales/bodegas/cajas
     * activas, desactiva el excedente automaticamente. La caja/sucursal/
     * bodega principal nunca se desactiva. No borra nada: solo cambia
     * `status`, asi que es reversible desde Estructura.
     */
    public function handle(Company $company, ?User $actor = null): void
    {
        $this->reconcile($company, Branch::class, 'max_branches', 'branch.auto_deactivated_on_plan_change', $actor);
        $this->reconcile($company, Warehouse::class, 'max_warehouses', 'warehouse.auto_deactivated_on_plan_change', $actor);
        $this->reconcile($company, CashRegister::class, 'max_cash_registers', 'cash_register.auto_deactivated_on_plan_change', $actor);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function reconcile(Company $company, string $modelClass, string $limitKey, string $auditAction, ?User $actor): void
    {
        $limit = $this->companyPlanResolver->limit($company, $limitKey);

        if ($limit === null) {
            return;
        }

        $active = $modelClass::query()
            ->where('company_id', $company->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        if ($active->count() <= $limit) {
            return;
        }

        foreach ($active->slice($limit) as $record) {
            /** @var Model $record */
            $before = $record->replicate()->forceFill(['id' => $record->getKey()]);

            $record->update(['status' => RecordStatus::Inactive->value]);

            $this->auditLogger->logUpdated($company, $auditAction, $before, $record->fresh(), $actor);
        }
    }
}
