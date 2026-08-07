<?php

namespace App\Livewire\Products;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Unit;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Products\ProductPresentationConverter;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProductPresentationsPage extends Component
{
    use AuthorizesRequests, InteractsWithToast;

    public ?int $editingPresentationId = null;
    public ?int $productId = null;
    public ?int $unitId = null;
    public string $name = '';
    public string $barcode = '';
    public string $conversionFactor = '1.000000';
    public string $price1 = '0.00';
    public string $price2 = '';
    public string $price3 = '';
    public string $samplePresentationQuantity = '1';
    public string $search = '';
    public bool $showArchived = false;

    public function mount(): void
    {
        $this->authorize('viewAny', ProductPresentation::class);
        abort_unless(
            app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'products.presentations'),
            403,
            'El plan actual no tiene habilitadas las presentaciones de productos.'
        );
    }

    public function savePresentation(): void
    {
        if ($this->editingPresentationId) {
            $this->authorize('update', $this->presentationsQuery()->findOrFail($this->editingPresentationId));
        } else {
            $this->authorize('create', ProductPresentation::class);
        }

        if (! $this->canCreatePresentations()) {
            $this->addError('productId', 'Debes tener al menos un producto y una unidad activa para registrar presentaciones.');

            return;
        }

        $company = $this->currentCompany();

        $validated = $this->validate([
            'productId' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'unitId' => [
                'required',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_presentations', 'name')
                    ->where(fn ($query) => $query
                        ->where('company_id', $company->id)
                        ->where('product_id', $this->productId))
                    ->ignore($this->editingPresentationId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('product_presentations', 'barcode')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingPresentationId),
            ],
            'conversionFactor' => ['required', 'numeric', 'gt:0'],
            'price1' => ['required', 'numeric', 'min:0'],
            'price2' => ['nullable', 'numeric', 'min:0'],
            'price3' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payload = [
            'product_id' => $validated['productId'],
            'unit_id' => $validated['unitId'],
            'name' => trim($validated['name']),
            'barcode' => $this->blankToNull(trim($validated['barcode'])),
            'conversion_factor' => $this->normalizeDecimal($validated['conversionFactor'], 6),
            'price_1' => $validated['price1'],
            'price_2' => $this->blankToNull($validated['price2']),
            'price_3' => $this->blankToNull($validated['price3']),
        ];

        if ($this->editingPresentationId) {
            $presentation = $this->presentationsQuery()->findOrFail($this->editingPresentationId);
            $presentation->update($payload);
        } else {
            ProductPresentation::query()->create([
                'company_id' => $company->id,
                'status' => RecordStatus::Active->value,
                ...$payload,
            ]);
        }

        $this->resetPresentationForm();
        $this->toast('Presentacion guardada correctamente.');
    }

    public function editPresentation(int $presentationId): void
    {
        $presentation = $this->presentationsQuery()->findOrFail($presentationId);
        $this->authorize('update', $presentation);

        $this->editingPresentationId = $presentation->id;
        $this->productId = $presentation->product_id;
        $this->unitId = $presentation->unit_id;
        $this->name = $presentation->name;
        $this->barcode = $presentation->barcode ?? '';
        $this->conversionFactor = (string) $presentation->conversion_factor;
        $this->price1 = (string) $presentation->price_1;
        $this->price2 = $presentation->price_2 !== null ? (string) $presentation->price_2 : '';
        $this->price3 = $presentation->price_3 !== null ? (string) $presentation->price_3 : '';
    }

    public function togglePresentationStatus(int $presentationId): void
    {
        $presentation = $this->presentationsQuery()->whereNull('deleted_at')->findOrFail($presentationId);
        $this->authorize('update', $presentation);

        $presentation->update([
            'status' => $presentation->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $presentation->status === RecordStatus::Active->value
                ? 'Presentacion activada correctamente.'
                : 'Presentacion desactivada correctamente.',
            'info'
        );
    }

    public function archivePresentation(int $presentationId): void
    {
        $presentation = $this->presentationsQuery()->whereNull('deleted_at')->findOrFail($presentationId);
        $this->authorize('archive', $presentation);

        $presentation->update([
            'status' => RecordStatus::Archived->value,
        ]);

        $presentation->delete();

        if ($this->editingPresentationId === $presentationId) {
            $this->resetPresentationForm();
        }

        $this->toast('Presentacion archivada correctamente.', 'warning');
    }

    public function restorePresentation(int $presentationId): void
    {
        $presentation = $this->presentationsQuery()->onlyTrashed()->findOrFail($presentationId);
        $this->authorize('restore', $presentation);

        $presentation->restore();
        $presentation->update([
            'status' => RecordStatus::Inactive->value,
        ]);

        $this->toast('Presentacion restaurada correctamente.');
    }

    public function resetPresentationForm(): void
    {
        $this->reset(
            'editingPresentationId',
            'productId',
            'unitId',
            'name',
            'barcode',
            'price2',
            'price3'
        );

        $this->conversionFactor = '1.000000';
        $this->price1 = '0.00';
        $this->samplePresentationQuantity = '1';
        $this->resetValidation();
    }

    public function presentations(): Collection
    {
        return $this->presentationsQuery()
            ->with(['product.baseUnit', 'unit'])
            ->when($this->showArchived, fn (Builder $query) => $query->withTrashed(), fn (Builder $query) => $query->whereNull('deleted_at'))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('name', $search)
                        ->orWhereLike('barcode', $search)
                        ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->whereLike('name', $search));
                });
            })
            ->orderBy('name')
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

    public function units(): Collection
    {
        return Unit::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->orderBy('name')
            ->get();
    }

    public function canCreatePresentations(): bool
    {
        return $this->products()->isNotEmpty() && $this->units()->isNotEmpty();
    }

    public function conversionPreview(): ?string
    {
        if (! is_numeric($this->samplePresentationQuantity) || ! is_numeric($this->conversionFactor)) {
            return null;
        }

        try {
            $result = app(ProductPresentationConverter::class)->toBaseQuantity(
                $this->normalizeDecimal($this->samplePresentationQuantity, 6),
                $this->normalizeDecimal($this->conversionFactor, 6),
                6,
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->trimDecimal($result);
    }

    public function render(): View
    {
        return view('livewire.products.product-presentations-page', [
            'presentations' => $this->presentations(),
            'products' => $this->products(),
            'units' => $this->units(),
            'canCreatePresentations' => $this->canCreatePresentations(),
            'conversionPreview' => $this->conversionPreview(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Presentaciones',
                'description' => 'Define empaques vendibles y su conversion exacta a la unidad base del producto.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function presentationsQuery()
    {
        return ProductPresentation::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    protected function normalizeDecimal(string|int|float $value, int $scale): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Decimal invalido.');
        }

        return bcadd($value, '0', $scale);
    }

    protected function trimDecimal(string $value): string
    {
        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
