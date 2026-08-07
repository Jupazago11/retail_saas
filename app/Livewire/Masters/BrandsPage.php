<?php

namespace App\Livewire\Masters;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Brand;
use App\Models\Company;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BrandsPage extends Component
{
    use AuthorizesRequests, InteractsWithToast;

    public ?int $editingBrandId = null;
    public string $name = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Brand::class);
    }

    public function saveBrand(): void
    {
        if ($this->editingBrandId) {
            $this->authorize('update', $this->brandsQuery()->findOrFail($this->editingBrandId));
        } else {
            $this->authorize('create', Brand::class);
        }

        $company = $this->currentCompany();

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'name')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingBrandId),
            ],
        ]);

        if ($this->editingBrandId) {
            $brand = $this->brandsQuery()->findOrFail($this->editingBrandId);

            $brand->update([
                'name' => trim($validated['name']),
            ]);
        } else {
            Brand::query()->create([
                'company_id' => $company->id,
                'name' => trim($validated['name']),
                'status' => RecordStatus::Active->value,
            ]);
        }

        $this->resetBrandForm();
        $this->toast('Marca guardada correctamente.');
    }

    public function editBrand(int $brandId): void
    {
        $brand = $this->brandsQuery()->findOrFail($brandId);
        $this->authorize('update', $brand);

        $this->editingBrandId = $brand->id;
        $this->name = $brand->name;
    }

    public function toggleBrandStatus(int $brandId): void
    {
        $brand = $this->brandsQuery()->whereNull('deleted_at')->findOrFail($brandId);
        $this->authorize('update', $brand);

        $brand->update([
            'status' => $brand->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $brand->status === RecordStatus::Active->value
                ? 'Marca activada correctamente.'
                : 'Marca desactivada correctamente.',
            'info'
        );
    }

    public function archiveBrand(int $brandId): void
    {
        $brand = $this->brandsQuery()->whereNull('deleted_at')->findOrFail($brandId);
        $this->authorize('archive', $brand);

        $brand->update([
            'status' => RecordStatus::Archived->value,
        ]);

        $brand->delete();

        if ($this->editingBrandId === $brandId) {
            $this->resetBrandForm();
        }

        $this->toast('Marca archivada correctamente.', 'warning');
    }

    public function restoreBrand(int $brandId): void
    {
        $brand = $this->brandsQuery()->onlyTrashed()->findOrFail($brandId);
        $this->authorize('restore', $brand);

        $brand->restore();
        $brand->update([
            'status' => RecordStatus::Inactive->value,
        ]);

        $this->toast('Marca restaurada correctamente.');
    }

    public function resetBrandForm(): void
    {
        $this->reset('editingBrandId', 'name');
        $this->resetValidation();
    }

    public function brands(): Collection
    {
        return $this->brandsQuery()
            ->withTrashed()
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.masters.brands-page', [
            'brands' => $this->brands(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Marcas',
                'description' => 'Administra las marcas maestras por empresa y conserva historico al archivarlas.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function brandsQuery()
    {
        return Brand::query()
            ->where('company_id', $this->currentCompany()->id);
    }
}
