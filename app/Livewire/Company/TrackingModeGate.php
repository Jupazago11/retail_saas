<?php

namespace App\Livewire\Company;

use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

// Modal obligatorio en el dashboard, solo para empresas del vertical
// restaurante: antes de continuar, elige si sus platos son productos
// simples o llevan receta (descuentan insumos al vender). La respuesta
// vive en company_settings (inventory.tracking_mode); mientras no se
// responda, el modal vuelve a aparecer en cada carga del dashboard.
class TrackingModeGate extends Component
{
    use InteractsWithToast;

    public string $trackingMode = 'simple';

    public function save(CompanySettings $companySettings): void
    {
        $this->ensurePermission('settings.manage');

        $this->validate([
            'trackingMode' => ['required', Rule::in(['simple', 'recipe'])],
        ]);

        $companySettings->set($this->currentCompany(), 'inventory', 'tracking_mode', $this->trackingMode);

        $this->toast('Modo de venta guardado correctamente.');
    }

    protected function pending(): bool
    {
        $company = $this->currentCompany();

        if ($company->businessType?->code !== 'restaurant') {
            return false;
        }

        return ! CompanySetting::query()
            ->where('company_id', $company->id)
            ->where('group_key', 'inventory')
            ->where('setting_key', 'tracking_mode')
            ->exists();
    }

    public function render(): View
    {
        return view('livewire.company.tracking-mode-gate', [
            'pending' => $this->pending(),
            'canManage' => auth()->user()?->hasCurrentCompanyPermission('settings.manage') ?? false,
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
