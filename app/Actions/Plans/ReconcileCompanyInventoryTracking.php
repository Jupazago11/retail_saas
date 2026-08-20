<?php

namespace App\Actions\Plans;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\CompanyPlanResolver;

class ReconcileCompanyInventoryTracking
{
    public function __construct(
        protected CompanyPlanResolver $companyPlanResolver,
        protected AuditLogger $auditLogger,
    ) {
    }

    /**
     * Si el plan efectivo de la empresa perdio el modulo `inventory` (bajo
     * de plan o se le quito por override), los productos que ya llevaban
     * `tracks_inventory = true` desde cuando si tenian el modulo se quedan
     * asi para siempre: nada vuelve a bajarlo. Eso hace que ventas/compras
     * sigan validando y descontando stock de un modulo que la empresa ya
     * no tiene contratado. Se apaga el flag (nunca se borra historial de
     * inventario ya posteado), igual que la estructura en
     * ReconcileCompanyStructureLimits: automatico al bajar, manual para
     * volver a activarlo si la empresa sube de plan otra vez.
     */
    public function handle(Company $company, ?User $actor = null): void
    {
        if ($this->companyPlanResolver->hasModule($company, 'inventory')) {
            return;
        }

        $affectedCount = Product::query()
            ->where('company_id', $company->id)
            ->where('tracks_inventory', true)
            ->count();

        if ($affectedCount === 0) {
            return;
        }

        Product::query()
            ->where('company_id', $company->id)
            ->where('tracks_inventory', true)
            ->update([
                'tracks_inventory' => false,
                'minimum_stock' => 0,
            ]);

        $this->auditLogger->logSnapshot(
            $company,
            'product.inventory_tracking_disabled_on_plan_change',
            Company::class,
            $company->id,
            ['tracks_inventory_products' => $affectedCount],
            ['tracks_inventory_products' => 0],
            $actor,
        );
    }
}
