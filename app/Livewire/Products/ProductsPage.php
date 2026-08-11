<?php

namespace App\Livewire\Products;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Plans\CompanyOperationalLimitGuard;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsPage extends Component
{
    use AuthorizesRequests, InteractsWithToast, WithPagination;

    public bool $showModal = false;
    public ?int $editingProductId = null;
    public ?int $categoryId = null;
    public ?int $brandId = null;
    public ?int $baseUnitId = null;
    public string $taxId = '';
    public string $name = '';
    public string $sku = '';
    public string $barcode = '';
    public string $description = '';
    public string $cost = '0.00';
    public string $price1 = '0.00';
    public string $price2 = '';
    public string $price3 = '';
    public bool $tracksInventory = true;
    public string $minimumStock = '0';
    public string $search = '';
    public bool $showArchived = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function lookupBarcode(): void
    {
        $barcode = trim($this->barcode);

        if ($barcode === '') {
            return;
        }

        $found = app(\App\Services\Products\OpenFoodFactsService::class)->findName($barcode);

        if ($found === null) {
            return;
        }

        if ($this->name === '') {
            $this->name = $found;
        }
    }

    public function updatedShowArchived(): void
    {
        $this->resetPage();
    }

    public function openModal(): void
    {
        $this->resetProductForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->resetProductForm();
    }

    public function saveQuickCategory(string $name): void
    {
        $name = trim($name);
        if (! $name) {
            return;
        }

        $company = $this->currentCompany();

        $category = Category::create([
            'company_id' => $company->id,
            'name'       => $name,
            'code'       => $this->uniqueCategoryCode($name, $company->id),
            'status'     => RecordStatus::Active->value,
        ]);

        $this->categoryId = $category->id;
    }

    protected function uniqueCategoryCode(string $name, int $companyId): string
    {
        $base = Str::upper(Str::slug($name, '_'));

        if ($base === '') {
            $base = Str::upper(Str::random(6));
        }

        $base = Str::substr($base, 0, 40);
        $code = $base;
        $n    = 2;

        while (Category::where('company_id', $companyId)->where('code', $code)->exists()) {
            $code = Str::substr($base, 0, 37).'_'.$n++;
        }

        return $code;
    }

    public function saveQuickBrand(string $name): void
    {
        $name = trim($name);
        if (! $name) {
            return;
        }
        $brand = Brand::create([
            'company_id' => $this->currentCompany()->id,
            'name' => $name,
            'status' => RecordStatus::Active->value,
        ]);
        $this->brandId = $brand->id;
    }

    public function saveQuickUnit(string $name, string $code): void
    {
        $name = trim($name);
        $code = strtoupper(trim($code));
        if (! $name || ! $code) {
            return;
        }
        $unit = Unit::create([
            'company_id' => $this->currentCompany()->id,
            'name' => $name,
            'code' => $code,
            'status' => RecordStatus::Active->value,
        ]);
        $this->baseUnitId = $unit->id;
    }

    public function saveProduct(): void
    {
        if ($this->editingProductId) {
            $this->authorize('update', $this->productsQuery()->findOrFail($this->editingProductId));
        } else {
            $this->authorize('create', Product::class);
        }

        if (! $this->canCreateProducts()) {
            $this->addError('categoryId', 'Debes crear al menos una categoria y una unidad antes de registrar productos.');

            return;
        }

        $company = $this->currentCompany();

        $validated = $this->validate([
            'categoryId' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'brandId' => [
                'nullable',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'baseUnitId' => [
                'required',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'taxId' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingProductId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('products', 'barcode')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($this->editingProductId),
            ],
            'description' => ['nullable', 'string'],
            'cost' => ['required', 'numeric', 'min:0'],
            'price1' => ['required', 'numeric', 'min:0'],
            'price2' => ['nullable', 'numeric', 'min:0'],
            'price3' => ['nullable', 'numeric', 'min:0'],
            'tracksInventory' => ['required', 'boolean'],
            'minimumStock' => ['required', 'integer', 'min:0'],
        ]);

        $hasInventory = app(\App\Services\Plans\CompanyPlanResolver::class)->hasModule($company, 'inventory');

        if (! $hasInventory) {
            $validated['tracksInventory'] = false;
            $validated['minimumStock']    = 0;
        }

        $payload = [
            'category_id' => $validated['categoryId'],
            'brand_id' => $validated['brandId'] ?: null,
            'base_unit_id' => $validated['baseUnitId'],
            'tax_id' => $this->blankToNull($validated['taxId']),
            'name' => trim($validated['name']),
            'sku' => $this->blankToNull(Str::upper(trim($validated['sku']))),
            'barcode' => $this->blankToNull(trim($validated['barcode'])),
            'description' => $this->blankToNull(trim($validated['description'])),
            'cost' => $validated['cost'],
            'price_1' => $validated['price1'],
            'price_2' => $this->blankToNull($validated['price2']),
            'price_3' => $this->blankToNull($validated['price3']),
            'margin_1' => $this->marginFrom($validated['cost'], $validated['price1']),
            'margin_2' => $this->marginFrom($validated['cost'], $validated['price2']),
            'margin_3' => $this->marginFrom($validated['cost'], $validated['price3']),
            'tracks_inventory' => $validated['tracksInventory'],
            'minimum_stock' => $validated['minimumStock'],
        ];

        if ($this->editingProductId) {
            $product = $this->productsQuery()->findOrFail($this->editingProductId);
            $product->update($payload);
        } else {
            try {
                app(CompanyOperationalLimitGuard::class)->ensureCanCreateProduct($company);

                Product::query()->create([
                    'company_id' => $company->id,
                    'status' => RecordStatus::Active->value,
                    ...$payload,
                ]);
            } catch (InvalidArgumentException $exception) {
                $this->addError('name', $exception->getMessage());

                return;
            }
        }

        $this->resetProductForm();
        $this->toast('Producto guardado correctamente.');
    }

    public function editProduct(int $productId): void
    {
        $product = $this->productsQuery()->findOrFail($productId);
        $this->authorize('update', $product);

        $this->showModal = true;
        $this->editingProductId = $product->id;
        $this->categoryId = $product->category_id;
        $this->brandId = $product->brand_id;
        $this->baseUnitId = $product->base_unit_id;
        $this->taxId = $product->tax_id ?? '';
        $this->name = $product->name;
        $this->sku = $product->sku ?? '';
        $this->barcode = $product->barcode ?? '';
        $this->description = $product->description ?? '';
        $this->cost   = $this->moneyToString($product->cost);
        $this->price1 = $this->moneyToString($product->price_1);
        $this->price2 = $product->price_2 !== null ? $this->moneyToString($product->price_2) : '';
        $this->price3 = $product->price_3 !== null ? $this->moneyToString($product->price_3) : '';
        $this->tracksInventory = $product->tracks_inventory;
        $this->minimumStock = (string) (int) $product->minimum_stock;
    }

    public function toggleProductStatus(int $productId): void
    {
        $product = $this->productsQuery()->whereNull('deleted_at')->findOrFail($productId);
        $this->authorize('update', $product);

        $product->update([
            'status' => $product->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $product->status === RecordStatus::Active->value
                ? 'Producto activado correctamente.'
                : 'Producto desactivado correctamente.',
            'info'
        );
    }

    public function archiveProduct(int $productId): void
    {
        $product = $this->productsQuery()->whereNull('deleted_at')->findOrFail($productId);
        $this->authorize('archive', $product);

        $product->update([
            'status' => RecordStatus::Archived->value,
        ]);

        $product->delete();

        if ($this->editingProductId === $productId) {
            $this->resetProductForm();
        }

        $this->toast('Producto archivado correctamente.', 'warning');
    }

    public function restoreProduct(int $productId): void
    {
        $product = $this->productsQuery()->onlyTrashed()->findOrFail($productId);
        $this->authorize('restore', $product);

        $product->restore();
        $product->update([
            'status' => RecordStatus::Inactive->value,
        ]);

        $this->toast('Producto restaurado correctamente.');
    }

    public function resetProductForm(): void
    {
        $this->showModal = false;
        $this->reset(
            'editingProductId',
            'categoryId',
            'brandId',
            'baseUnitId',
            'taxId',
            'name',
            'sku',
            'barcode',
            'description',
            'price2',
            'price3'
        );

        $this->cost   = '0';
        $this->price1 = '0';
        $this->tracksInventory = true;
        $this->minimumStock    = '0';
        $this->resetValidation();
    }

    public function products(): LengthAwarePaginator
    {
        return $this->productsQuery()
            ->with(['category', 'brand', 'baseUnit'])
            ->when($this->showArchived, fn (Builder $query) => $query->withTrashed(), fn (Builder $query) => $query->whereNull('deleted_at'))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('name', $search)
                        ->orWhereLike('barcode', $search);
                });
            })
            ->orderBy('name')
            ->paginate(10);
    }

    public function categories(): Collection
    {
        return Category::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    public function brands(): Collection
    {
        return Brand::query()
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

    public function canCreateProducts(): bool
    {
        return $this->categories()->isNotEmpty() && $this->units()->isNotEmpty();
    }

    public function render(): View
    {
        $company = $this->currentCompany();

        return view('livewire.products.products-page', [
            'products' => $this->products(),
            'categories' => $this->categories(),
            'brands' => $this->brands(),
            'units' => $this->units(),
            'canCreateProducts' => $this->canCreateProducts(),
            'hasInventory' => app(\App\Services\Plans\CompanyPlanResolver::class)->hasModule($company, 'inventory'),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Productos',
                'description' => 'Define el catalogo base con precios, unidad principal y control inicial de inventario.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function productsQuery()
    {
        return Product::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    protected function moneyToString(mixed $value): string
    {
        return (string) (int) round((float) $value);
    }

    protected function marginFrom(string|float|int $cost, string|float|int|null $price): ?string
    {
        if ($price === null || $price === '') {
            return null;
        }

        $cost = (float) $cost;
        $price = (float) $price;

        if ($cost <= 0 || $price <= 0) {
            return null;
        }

        return number_format((($price - $cost) / $cost) * 100, 2, '.', '');
    }
}
