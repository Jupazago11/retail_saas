<?php

namespace App\Livewire\Masters;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Unit;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\Component;

class UnitsPage extends Component
{
    use AuthorizesRequests, InteractsWithToast;

    public ?int $editingUnitId = null;
    public string $code = '';
    public string $name = '';
    public int $precisionScale = 0;

    public function mount(): void
    {
        $this->authorize('viewAny', Unit::class);
    }

    public function saveUnit(): void
    {
        if ($this->editingUnitId) {
            $this->authorize('update', $this->unitsQuery()->findOrFail($this->editingUnitId));
        } else {
            $this->authorize('create', Unit::class);
        }

        $company = $this->currentCompany();

        $validated = $this->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('units', 'code')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingUnitId),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'name')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingUnitId),
            ],
            'precisionScale' => ['required', 'integer', 'min:0', 'max:6'],
        ]);

        if ($this->editingUnitId) {
            $unit = $this->unitsQuery()->findOrFail($this->editingUnitId);

            $unit->update([
                'code' => Str::upper(trim($validated['code'])),
                'name' => trim($validated['name']),
                'precision_scale' => $validated['precisionScale'],
            ]);
        } else {
            Unit::query()->create([
                'company_id' => $company->id,
                'code' => Str::upper(trim($validated['code'])),
                'name' => trim($validated['name']),
                'precision_scale' => $validated['precisionScale'],
                'status' => RecordStatus::Active->value,
            ]);
        }

        $this->resetUnitForm();
        $this->toast('Unidad guardada correctamente.');
    }

    public function editUnit(int $unitId): void
    {
        $unit = $this->unitsQuery()->findOrFail($unitId);
        $this->authorize('update', $unit);

        $this->editingUnitId = $unit->id;
        $this->code = $unit->code;
        $this->name = $unit->name;
        $this->precisionScale = $unit->precision_scale;
    }

    public function toggleUnitStatus(int $unitId): void
    {
        $unit = $this->unitsQuery()->findOrFail($unitId);
        $this->authorize('update', $unit);

        if ($unit->status === RecordStatus::Archived->value) {
            return;
        }

        $unit->update([
            'status' => $unit->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $unit->status === RecordStatus::Active->value
                ? 'Unidad activada correctamente.'
                : 'Unidad desactivada correctamente.',
            'info'
        );
    }

    public function archiveUnit(int $unitId): void
    {
        $unit = $this->unitsQuery()->findOrFail($unitId);
        $this->authorize('archive', $unit);

        $unit->update([
            'status' => RecordStatus::Archived->value,
        ]);

        if ($this->editingUnitId === $unitId) {
            $this->resetUnitForm();
        }

        $this->toast('Unidad archivada correctamente.', 'warning');
    }

    public function restoreUnit(int $unitId): void
    {
        $unit = $this->unitsQuery()->findOrFail($unitId);
        $this->authorize('restore', $unit);

        $unit->update([
            'status' => RecordStatus::Inactive->value,
        ]);

        $this->toast('Unidad restaurada correctamente.');
    }

    public function resetUnitForm(): void
    {
        $this->reset('editingUnitId', 'code', 'name');
        $this->precisionScale = 0;
        $this->resetValidation();
    }

    public function units(): Collection
    {
        return $this->unitsQuery()
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.masters.units-page', [
            'units' => $this->units(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Unidades',
                'description' => 'Define unidades base y de presentacion con control de precision por empresa.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function unitsQuery()
    {
        return Unit::query()
            ->where('company_id', $this->currentCompany()->id);
    }
}
