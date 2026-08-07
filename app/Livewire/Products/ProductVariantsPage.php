<?php

namespace App\Livewire\Products;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProductVariantsPage extends Component
{
    use AuthorizesRequests, InteractsWithToast;

    public ?int $editingVariantId = null;
    public ?int $productId = null;
    public string $sku = '';
    public string $barcode = '';
    public string $priceOverride = '';
    public array $selectedAttributeValueIds = [];
    public string $search = '';
    public bool $showArchived = false;

    public function mount(): void
    {
        $this->authorize('viewAny', ProductVariant::class);
        abort_unless(
            app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'products.variants'),
            403,
            'El plan actual no tiene habilitadas las variantes de productos.'
        );
    }

    public function saveVariant(): void
    {
        if ($this->editingVariantId) {
            $this->authorize('update', $this->variantsQuery()->findOrFail($this->editingVariantId));
        } else {
            $this->authorize('create', ProductVariant::class);
        }

        if (! $this->canCreateVariants()) {
            $this->addError('productId', 'Debes tener al menos un producto y atributos con valores activos para crear variantes.');

            return;
        }

        $company = $this->currentCompany();

        $validated = $this->validate([
            'productId' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('product_variants', 'sku')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingVariantId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('product_variants', 'barcode')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingVariantId),
            ],
            'priceOverride' => ['nullable', 'numeric', 'min:0'],
            'selectedAttributeValueIds' => ['required', 'array', 'min:1'],
        ]);

        $valueIds = collect($validated['selectedAttributeValueIds'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $attributeValues = AttributeValue::query()
            ->whereIn('id', $valueIds)
            ->where('status', RecordStatus::Active->value)
            ->whereHas('attribute', function (Builder $query) use ($company) {
                $query
                    ->where('company_id', $company->id)
                    ->where('status', RecordStatus::Active->value);
            })
            ->with('attribute')
            ->get();

        if ($attributeValues->count() !== $valueIds->count()) {
            $this->addError('selectedAttributeValueIds', 'Selecciona solo valores activos de atributos de la empresa actual.');

            return;
        }

        if ($attributeValues->pluck('attribute_id')->unique()->count() !== $attributeValues->count()) {
            $this->addError('selectedAttributeValueIds', 'Solo puedes seleccionar un valor por cada atributo.');

            return;
        }

        if ($this->variantCombinationExists((int) $validated['productId'], $valueIds->all(), $this->editingVariantId)) {
            $this->addError('selectedAttributeValueIds', 'Ya existe una variante con esa misma combinacion de valores.');

            return;
        }

        $payload = [
            'product_id' => $validated['productId'],
            'sku' => $this->blankToNull($validated['sku']),
            'barcode' => $this->blankToNull($validated['barcode']),
            'price_override' => $this->blankToNull($validated['priceOverride']),
        ];

        if ($this->editingVariantId) {
            $variant = $this->variantsQuery()->findOrFail($this->editingVariantId);
            $variant->update($payload);
        } else {
            $variant = ProductVariant::query()->create([
                'company_id' => $company->id,
                'status' => RecordStatus::Active->value,
                ...$payload,
            ]);
        }

        $variant->attributeValues()->sync($valueIds->all());

        $this->resetVariantForm();
        $this->toast('Variante guardada correctamente.');
    }

    public function editVariant(int $variantId): void
    {
        $variant = $this->variantsQuery()
            ->with('attributeValues')
            ->findOrFail($variantId);
        $this->authorize('update', $variant);

        $this->editingVariantId = $variant->id;
        $this->productId = $variant->product_id;
        $this->sku = $variant->sku ?? '';
        $this->barcode = $variant->barcode ?? '';
        $this->priceOverride = $variant->price_override !== null ? (string) $variant->price_override : '';
        $this->selectedAttributeValueIds = $variant->attributeValues->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function toggleVariantStatus(int $variantId): void
    {
        $variant = $this->variantsQuery()->findOrFail($variantId);
        $this->authorize('update', $variant);

        $variant->update([
            'status' => $variant->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $variant->status === RecordStatus::Active->value
                ? 'Variante activada correctamente.'
                : 'Variante desactivada correctamente.',
            'info'
        );
    }

    public function archiveVariant(int $variantId): void
    {
        $variant = $this->variantsQuery()->findOrFail($variantId);
        $this->authorize('archive', $variant);

        $variant->update([
            'status' => RecordStatus::Archived->value,
        ]);

        if ($this->editingVariantId === $variantId) {
            $this->resetVariantForm();
        }

        $this->toast('Variante archivada correctamente.', 'warning');
    }

    public function restoreVariant(int $variantId): void
    {
        $variant = $this->variantsQuery()->findOrFail($variantId);
        $this->authorize('restore', $variant);

        $variant->update([
            'status' => RecordStatus::Inactive->value,
        ]);

        $this->toast('Variante restaurada correctamente.');
    }

    public function resetVariantForm(): void
    {
        $this->reset('editingVariantId', 'productId', 'sku', 'barcode', 'priceOverride');
        $this->selectedAttributeValueIds = [];
        $this->resetValidation();
    }

    public function variants(): Collection
    {
        return $this->variantsQuery()
            ->with(['product', 'attributeValues.attribute'])
            ->when(! $this->showArchived, fn (Builder $query) => $query->where('status', '!=', RecordStatus::Archived->value))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('sku', $search)
                        ->orWhereLike('barcode', $search)
                        ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->whereLike('name', $search));
                });
            })
            ->orderBy('id', 'desc')
            ->get();
    }

    public function products(): Collection
    {
        return Product::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    public function attributes(): Collection
    {
        return Attribute::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->with(['values' => fn ($query) => $query
                ->where('status', RecordStatus::Active->value)
                ->orderBy('value')])
            ->orderBy('name')
            ->get();
    }

    public function canCreateVariants(): bool
    {
        return $this->products()->isNotEmpty()
            && $this->attributes()->contains(fn ($attribute) => $attribute->values->isNotEmpty());
    }

    public function render(): View
    {
        return view('livewire.products.product-variants-page', [
            'variants' => $this->variants(),
            'products' => $this->products(),
            'attributes' => $this->attributes(),
            'canCreateVariants' => $this->canCreateVariants(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Variantes',
                'description' => 'Construye combinaciones vendibles por producto a partir de atributos y valores activos.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function variantsQuery()
    {
        return ProductVariant::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function variantCombinationExists(int $productId, array $valueIds, ?int $ignoreVariantId = null): bool
    {
        $candidate = collect($valueIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $this->variantsQuery()
            ->where('product_id', $productId)
            ->when($ignoreVariantId, fn (Builder $query) => $query->whereKeyNot($ignoreVariantId))
            ->with('attributeValues')
            ->get()
            ->contains(function (ProductVariant $variant) use ($candidate) {
                $current = $variant->attributeValues->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

                return $current === $candidate;
            });
    }

    protected function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
