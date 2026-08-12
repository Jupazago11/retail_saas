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

    public function otherPlans(): Collection
    {
        $currentPlanId = $this->effectiveSnapshot()['plan']?->id;

        return Plan::query()
            ->where('status', RecordStatus::Active->value)
            ->when($currentPlanId, fn ($q) => $q->where('id', '!=', $currentPlanId))
            ->with('modules')
            ->orderBy('base_price')
            ->get();
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
        return view('livewire.admin.subscription-page', [
            'snapshot' => $this->effectiveSnapshot(),
            'otherPlans' => $this->otherPlans(),
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
