<?php

namespace App\Livewire\Admin;

use App\Enums\EquipmentRentalStatus;
use App\Enums\EquipmentType;
use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\EquipmentRental;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use App\Support\EquipmentRentalCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class SubscriptionPage extends Component
{
    public function mount(): void
    {
        $this->ensurePermission('settings.manage');
    }

    public function effectiveSnapshot(): array
    {
        return app(CompanyPlanResolver::class)->snapshot($this->currentCompany());
    }

    /**
     * Planes ordenados de menor a mayor "tamano" (cantidad de modulos
     * incluidos). No se ordena por precio porque en este catalogo todos
     * los planes tienen base_price 0 — el conteo de modulos es el proxy
     * real de que tan basico o completo es cada plan.
     */
    public function allPlans(): Collection
    {
        return Plan::query()
            ->where('status', RecordStatus::Active->value)
            ->with([
                // Plan::modules() no filtra el pivot "enabled" por defecto
                // (solo lo expone via withPivot); sin este filtro, modulos
                // deshabilitados para un plan igual aparecen en la lista.
                'modules' => fn ($query) => $query->wherePivot('enabled', true),
                'limits',
            ])
            ->get()
            ->sortBy(fn (Plan $plan) => $plan->modules->count())
            ->values();
    }

    /**
     * Para cada plan (ya ordenado de mas basico a mas completo), calcula
     * que modulos suma respecto al plan inmediatamente anterior. El
     * primer plan (el mas basico) muestra todos sus modulos sin
     * comparar contra nada. Ej: Basic -> [Productos, POS, Caja,
     * Reportes]; Pro -> "incluye todo lo de Basic, mas" [Inventario,
     * Compras, Credito, ...].
     */
    public function planModuleBreakdown(Collection $plans): array
    {
        $breakdown = [];
        $previousPlan = null;

        foreach ($plans as $plan) {
            $previousModuleCodes = $previousPlan?->modules->pluck('code')->all() ?? [];

            $breakdown[$plan->id] = [
                'previous_plan_name' => $previousPlan?->name,
                'added_modules' => $plan->modules
                    ->reject(fn ($module) => in_array($module->code, $previousModuleCodes, true))
                    ->values(),
            ];

            $previousPlan = $plan;
        }

        return $breakdown;
    }

    public function whatsappUrl(): string
    {
        return PlatformSetting::whatsappUrl(
            'Hola, quiero enviar el comprobante de pago de la suscripcion de '.$this->currentCompany()->display_name.'.'
        );
    }

    /**
     * Solo lectura: el alquiler de equipos lo activa/gestiona el equipo de
     * plataforma (Plataforma > Suscripciones), no la empresa cliente.
     */
    public function activeEquipmentRentals(EquipmentType $type): Collection
    {
        return EquipmentRental::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('equipment_type', $type->value)
            ->where('status', EquipmentRentalStatus::Active->value)
            ->get();
    }

    public function requestedEquipmentCount(EquipmentType $type): int
    {
        return EquipmentRental::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('equipment_type', $type->value)
            ->where('status', EquipmentRentalStatus::Requested->value)
            ->count();
    }

    public function equipmentMonthlyTotal(): float
    {
        return EquipmentRentalCatalog::monthlyTotalForActiveCounts(
            $this->activeEquipmentRentals(EquipmentType::ThermalPrinter)->count(),
            $this->activeEquipmentRentals(EquipmentType::BarcodeScanner)->count(),
        );
    }

    public function formatMoney(mixed $value): string
    {
        return \App\Support\Money::format((float) $value);
    }

    public function render(): View
    {
        $plans = $this->allPlans();

        return view('livewire.admin.subscription-page', [
            'snapshot' => $this->effectiveSnapshot(),
            'plans' => $plans,
            'planModuleBreakdown' => $this->planModuleBreakdown($plans),
            'equipmentTypes' => EquipmentType::cases(),
            'equipmentMonthlyTotal' => $this->equipmentMonthlyTotal(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Suscripcion',
                'description' => 'Consulta tu plan actual, la fecha de renovacion y los demas planes disponibles.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }
}
