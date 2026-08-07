<?php

namespace App\Livewire\Products;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Company;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AttributesPage extends Component
{
    use AuthorizesRequests, InteractsWithToast;

    public ?int $editingAttributeId = null;
    public string $name = '';
    public string $code = '';
    public ?int $selectedAttributeId = null;
    public ?int $editingValueId = null;
    public string $value = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Attribute::class);
    }

    public function saveAttribute(): void
    {
        if ($this->editingAttributeId) {
            $this->authorize('update', $this->attributesQuery()->findOrFail($this->editingAttributeId));
        } else {
            $this->authorize('create', Attribute::class);
        }

        $company = $this->currentCompany();

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('attributes', 'name')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingAttributeId),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('attributes', 'code')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingAttributeId),
            ],
        ]);

        if ($this->editingAttributeId) {
            $attribute = $this->attributesQuery()->findOrFail($this->editingAttributeId);

            $attribute->update([
                'name' => trim($validated['name']),
                'code' => Str::upper(trim($validated['code'])),
            ]);
        } else {
            $attribute = Attribute::query()->create([
                'company_id' => $company->id,
                'name' => trim($validated['name']),
                'code' => Str::upper(trim($validated['code'])),
                'status' => RecordStatus::Active->value,
            ]);
        }

        $this->selectedAttributeId = $attribute->id;
        $this->resetAttributeForm();
        $this->toast('Atributo guardado correctamente.');
    }

    public function editAttribute(int $attributeId): void
    {
        $attribute = $this->attributesQuery()->findOrFail($attributeId);
        $this->authorize('update', $attribute);

        $this->editingAttributeId = $attribute->id;
        $this->selectedAttributeId = $attribute->id;
        $this->name = $attribute->name;
        $this->code = $attribute->code;
    }

    public function selectAttribute(int $attributeId): void
    {
        $attribute = $this->attributesQuery()->findOrFail($attributeId);
        $this->authorize('view', $attribute);

        $this->selectedAttributeId = $attribute->id;
        $this->resetValueForm();
    }

    public function toggleAttributeStatus(int $attributeId): void
    {
        $attribute = $this->attributesQuery()->findOrFail($attributeId);
        $this->authorize('update', $attribute);

        $attribute->update([
            'status' => $attribute->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $attribute->status === RecordStatus::Active->value
                ? 'Atributo activado correctamente.'
                : 'Atributo desactivado correctamente.',
            'info'
        );
    }

    public function archiveAttribute(int $attributeId): void
    {
        $attribute = $this->attributesQuery()->findOrFail($attributeId);
        $this->authorize('archive', $attribute);

        $attribute->update([
            'status' => RecordStatus::Archived->value,
        ]);

        $this->toast('Atributo archivado correctamente.', 'warning');
    }

    public function restoreAttribute(int $attributeId): void
    {
        $attribute = $this->attributesQuery()->findOrFail($attributeId);
        $this->authorize('restore', $attribute);

        $attribute->update([
            'status' => RecordStatus::Inactive->value,
        ]);

        $this->toast('Atributo restaurado correctamente.');
    }

    public function saveValue(): void
    {
        $attribute = $this->selectedAttribute();

        abort_unless($attribute, 404);
        $this->authorize($this->editingValueId ? 'update' : 'create', $this->editingValueId ? $attribute : Attribute::class);

        $validated = $this->validate([
            'value' => [
                'required',
                'string',
                'max:255',
                Rule::unique('attribute_values', 'value')
                    ->where(fn ($query) => $query->where('attribute_id', $attribute->id))
                    ->ignore($this->editingValueId),
            ],
        ]);

        if ($this->editingValueId) {
            $value = $attribute->values()->findOrFail($this->editingValueId);

            $value->update([
                'value' => trim($validated['value']),
            ]);
        } else {
            $attribute->values()->create([
                'value' => trim($validated['value']),
                'status' => RecordStatus::Active->value,
            ]);
        }

        $this->resetValueForm();
        $this->toast('Valor de atributo guardado correctamente.');
    }

    public function editValue(int $valueId): void
    {
        $value = $this->attributeValuesQuery()->with('attribute')->findOrFail($valueId);
        $this->authorize('update', $value->attribute);

        $this->editingValueId = $value->id;
        $this->selectedAttributeId = $value->attribute_id;
        $this->value = $value->value;
    }

    public function toggleValueStatus(int $valueId): void
    {
        $value = $this->attributeValuesQuery()->findOrFail($valueId);
        $this->authorize('update', $value->attribute);

        $value->update([
            'status' => $value->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $value->status === RecordStatus::Active->value
                ? 'Valor activado correctamente.'
                : 'Valor desactivado correctamente.',
            'info'
        );
    }

    public function archiveValue(int $valueId): void
    {
        $value = $this->attributeValuesQuery()->findOrFail($valueId);
        $this->authorize('archive', $value->attribute);

        $value->update([
            'status' => RecordStatus::Archived->value,
        ]);

        $this->toast('Valor archivado correctamente.', 'warning');
    }

    public function restoreValue(int $valueId): void
    {
        $value = $this->attributeValuesQuery()->findOrFail($valueId);
        $this->authorize('restore', $value->attribute);

        $value->update([
            'status' => RecordStatus::Inactive->value,
        ]);

        $this->toast('Valor restaurado correctamente.');
    }

    public function resetAttributeForm(): void
    {
        $this->reset('editingAttributeId', 'name', 'code');
        $this->resetValidation();
    }

    public function resetValueForm(): void
    {
        $this->reset('editingValueId', 'value');
        $this->resetValidation();
    }

    public function attributes(): Collection
    {
        return $this->attributesQuery()
            ->with('values')
            ->orderBy('name')
            ->get();
    }

    public function selectedAttribute(): ?Attribute
    {
        if (! $this->selectedAttributeId) {
            return null;
        }

        return $this->attributesQuery()
            ->with('values')
            ->find($this->selectedAttributeId);
    }

    public function render(): View
    {
        return view('livewire.products.attributes-page', [
            'attributes' => $this->attributes(),
            'selectedAttribute' => $this->selectedAttribute(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Atributos',
                'description' => 'Define ejes de variantes como talla, color o sabor y administra sus valores por empresa.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function attributesQuery()
    {
        return Attribute::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function attributeValuesQuery()
    {
        return AttributeValue::query()
            ->whereHas('attribute', fn ($query) => $query->where('company_id', $this->currentCompany()->id));
    }
}
