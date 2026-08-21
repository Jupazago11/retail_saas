<?php

namespace App\Livewire\Company;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\CashRegister;
use App\Models\Company;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

// Modal obligatorio en el dashboard: bloquea el menu (sin boton de cerrar)
// mientras la empresa tenga alguna caja activa sin tipo de impresora
// asignado. Solo dispara para cajas nuevas (la migracion que agrego esta
// columna ya dejo backfilled las cajas de empresas existentes).
class PrinterSetupGate extends Component
{
    use InteractsWithToast;

    public array $printerTypes = [];

    public function mount(): void
    {
        foreach ($this->pending() as $cashRegister) {
            $this->printerTypes[$cashRegister->id] = 'thermal_58mm';
        }
    }

    public function save(): void
    {
        $this->ensurePermission('settings.manage');

        // No confiar en las claves que llegan del cliente: solo se procesan
        // las cajas que realmente siguen pendientes en el servidor.
        $pendingIds = $this->pending()->pluck('id')->all();
        $submitted = array_intersect_key($this->printerTypes, array_flip($pendingIds));

        $this->validate(
            collect($submitted)
                ->mapWithKeys(fn ($value, $id) => ["printerTypes.{$id}" => ['required', Rule::in(array_keys(CashRegister::PRINTER_TYPES))]])
                ->all()
        );

        foreach ($submitted as $id => $printerType) {
            CashRegister::query()
                ->whereKey($id)
                ->whereNull('printer_type')
                ->update(['printer_type' => $printerType]);
        }

        $this->toast('Configuracion de impresora guardada correctamente.');
    }

    protected function pending(): Collection
    {
        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('printer_type')
            ->whereNull('deleted_at')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.company.printer-setup-gate', [
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
