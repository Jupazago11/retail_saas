<?php

namespace App\Livewire\Dining;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\DiningFloorPlan;
use App\Models\DiningTable;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class DiningTablesPage extends Component
{
    use InteractsWithToast;

    public bool $showModal = false;
    public ?int $editingId = null;
    public ?int $branchId = null;
    public string $name = '';
    public string $capacity = '';

    public function mount(): void
    {
        $this->ensurePermission('dining.manage');
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->branchId = $this->branches()->first()?->id;
        $this->showModal = true;
    }

    public function startEdit(int $id): void
    {
        $table = $this->tablesQuery()->findOrFail($id);

        $this->editingId = $table->id;
        $this->branchId = $table->branch_id;
        $this->name = $table->name;
        $this->capacity = $table->capacity ? (string) $table->capacity : '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->ensurePermission('dining.manage');

        $company = $this->currentCompany();

        if ($this->editingId) {
            $validated = $this->validate([
                'branchId' => [
                    'required',
                    Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $company->id)->whereNull('deleted_at')),
                ],
                'name' => [
                    'required',
                    'string',
                    'max:60',
                    // Solo choca contra otra mesa ACTIVA: una archivada ya no
                    // reserva su numero (ver DiningTable::renumberActiveTables()).
                    Rule::unique('dining_tables', 'name')
                        ->where(fn ($q) => $q->where('company_id', $company->id)
                            ->where('branch_id', $this->branchId)
                            ->where('status', RecordStatus::Active->value))
                        ->ignore($this->editingId),
                ],
                'capacity' => ['nullable', 'integer', 'min:1'],
            ]);

            $this->tablesQuery()->findOrFail($this->editingId)->update([
                'branch_id' => (int) $validated['branchId'],
                'name' => trim($validated['name']),
                'capacity' => $validated['capacity'] !== '' ? (int) $validated['capacity'] : null,
            ]);
        } else {
            $validated = $this->validate([
                'branchId' => [
                    'required',
                    Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $company->id)->whereNull('deleted_at')),
                ],
                'capacity' => ['nullable', 'integer', 'min:1'],
            ]);

            // El numero de la mesa se asigna solo (siempre el siguiente libre,
            // arrancando en 1) — no es un campo que la empresa escriba a mano,
            // asi el mismo esquema de numeracion aplica sin importar si la
            // mesa se crea aqui o desde el editor visual del plano.
            DiningTable::query()->create([
                'company_id' => $company->id,
                'branch_id' => (int) $validated['branchId'],
                'name' => DiningTable::nextNumberFor($company->id, (int) $validated['branchId']),
                'capacity' => $validated['capacity'] !== '' ? (int) $validated['capacity'] : null,
                'status' => RecordStatus::Active->value,
                'occupancy_status' => 'free',
            ]);
        }

        $this->closeModal();
        $this->toast('Mesa guardada correctamente.');
    }

    public function toggleStatus(int $id): void
    {
        $this->ensurePermission('dining.manage');

        $table = $this->tablesQuery()->findOrFail($id);
        $wasActive = $table->status === RecordStatus::Active->value;
        $table->update(['status' => $wasActive ? RecordStatus::Inactive->value : RecordStatus::Active->value]);

        // Al archivar (no al reactivar) se cierra el hueco que deja en la
        // numeracion — mismo comportamiento que borrar una mesa desde el
        // editor visual del plano.
        if ($wasActive) {
            DiningTable::renumberActiveTables($table->company_id, $table->branch_id);
        }

        $this->toast('Estado de la mesa actualizado.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function branches(): Collection
    {
        return Branch::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function tables(): Collection
    {
        return $this->tablesQuery()
            ->where('status', RecordStatus::Active->value)
            ->with(['branch', 'frozenSales' => fn ($q) => $q->where('status', 'open')->latest('id')->limit(1)])
            ->orderBy('branch_id')
            ->get()
            // orderBy('name') ordenaria como texto ("10" antes que "2") ahora
            // que el numero de mesa es siempre numerico.
            ->sortBy(fn (DiningTable $table) => is_numeric($table->name) ? (int) $table->name : PHP_INT_MAX)
            ->values();
    }

    public function floorPlans(): Collection
    {
        return DiningFloorPlan::query()
            ->where('company_id', $this->currentCompany()->id)
            ->get()
            ->keyBy('branch_id');
    }

    public function placedCashRegisters(): Collection
    {
        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNotNull('pos_x')
            ->get()
            ->groupBy('branch_id');
    }

    public function isOwner(): bool
    {
        return auth()->id() === $this->currentCompany()->owner_user_id;
    }

    public function render(): View
    {
        return view('livewire.dining.dining-tables-page', [
            'tables' => $this->tables(),
            'branches' => $this->branches(),
            'floorPlans' => $this->floorPlans(),
            'placedCashRegisters' => $this->placedCashRegisters(),
            'isOwner' => $this->isOwner(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Mesas y comandas',
                'description' => 'Plano de mesas: abre una comanda, agrega platos y cobra cuando el cliente pida la cuenta.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function tablesQuery()
    {
        return DiningTable::query()->where('company_id', $this->currentCompany()->id);
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->branchId = null;
        $this->name = '';
        $this->capacity = '';
        $this->resetValidation();
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
