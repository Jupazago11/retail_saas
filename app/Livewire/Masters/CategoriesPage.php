<?php

namespace App\Livewire\Masters;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Category;
use App\Models\Company;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\Component;

class CategoriesPage extends Component
{
    use AuthorizesRequests, InteractsWithToast;

    public ?int $editingCategoryId = null;
    public string $name = '';
    public string $code = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
    }

    public function saveCategory(): void
    {
        if ($this->editingCategoryId) {
            $this->authorize('update', $this->categoriesQuery()->findOrFail($this->editingCategoryId));
        } else {
            $this->authorize('create', Category::class);
        }

        $company = $this->currentCompany();

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingCategoryId),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('categories', 'code')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingCategoryId),
            ],
        ]);

        if ($this->editingCategoryId) {
            $category = $this->categoriesQuery()->findOrFail($this->editingCategoryId);

            $category->update([
                'name' => trim($validated['name']),
                'code' => Str::upper(trim($validated['code'])),
            ]);
        } else {
            Category::query()->create([
                'company_id' => $company->id,
                'name' => trim($validated['name']),
                'code' => Str::upper(trim($validated['code'])),
                'status' => RecordStatus::Active->value,
            ]);
        }

        $this->resetCategoryForm();
        $this->toast('Categoria guardada correctamente.');
    }

    public function editCategory(int $categoryId): void
    {
        $category = $this->categoriesQuery()->findOrFail($categoryId);
        $this->authorize('update', $category);

        $this->editingCategoryId = $category->id;
        $this->name = $category->name;
        $this->code = $category->code;
    }

    public function toggleCategoryStatus(int $categoryId): void
    {
        $category = $this->categoriesQuery()->whereNull('deleted_at')->findOrFail($categoryId);
        $this->authorize('update', $category);

        $category->update([
            'status' => $category->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $category->status === RecordStatus::Active->value
                ? 'Categoria activada correctamente.'
                : 'Categoria desactivada correctamente.',
            'info'
        );
    }

    public function archiveCategory(int $categoryId): void
    {
        $category = $this->categoriesQuery()->whereNull('deleted_at')->findOrFail($categoryId);
        $this->authorize('archive', $category);

        $category->update([
            'status' => RecordStatus::Archived->value,
        ]);

        $category->delete();

        if ($this->editingCategoryId === $categoryId) {
            $this->resetCategoryForm();
        }

        $this->toast('Categoria archivada correctamente.', 'warning');
    }

    public function restoreCategory(int $categoryId): void
    {
        $category = $this->categoriesQuery()->onlyTrashed()->findOrFail($categoryId);
        $this->authorize('restore', $category);

        $category->restore();
        $category->update([
            'status' => RecordStatus::Inactive->value,
        ]);

        $this->toast('Categoria restaurada correctamente.');
    }

    public function resetCategoryForm(): void
    {
        $this->reset('editingCategoryId', 'name', 'code');
        $this->resetValidation();
    }

    public function categories(): Collection
    {
        return $this->categoriesQuery()
            ->withTrashed()
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.masters.categories-page', [
            'categories' => $this->categories(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Categorias',
                'description' => 'Clasifica productos por empresa con codigos propios y control de estado.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function categoriesQuery()
    {
        return Category::query()
            ->where('company_id', $this->currentCompany()->id);
    }
}
