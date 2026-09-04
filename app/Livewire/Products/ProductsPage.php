<?php

namespace App\Livewire\Products;

use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Products\ResolveOrCreateBrand;
use App\Actions\Products\ResolveOrCreateCategory;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\HasResponsivePageSize;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Plans\CompanyOperationalLimitGuard;
use App\Services\Tenancy\CurrentCompany;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Throwable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsPage extends Component
{
    use AuthorizesRequests, HasResponsivePageSize, InteractsWithToast, WithPagination;

    public int $perPage = 10;

    public bool $showModal = false;
    public ?int $editingProductId = null;
    // Aceptan tambien un string (nombre libre tipeado en el combobox) para
    // que saveProduct() pueda crear la categoria/marca al vuelo si no
    // coincide con ninguna existente; ver resolveCategoryValue()/resolveBrandValue().
    public int|string|null $categoryId = null;
    public int|string|null $brandId = null;
    public ?int $supplierId = null;
    public string $taxId = '';
    public string $name = '';
    public string $sku = '';
    public string $barcode = '';
    public string $description = '';
    public string $cost = '0.00';
    public string $taxRate = '0';
    public string $price1 = '0.00';
    public string $price2 = '';
    public string $price3 = '';
    // Perecederos/graneles (papa, yuca, frijol...) con precio que cambia a
    // diario: en vez de mantener price_1/2/3 actualizados cada dia, el
    // producto queda sin precio de catalogo (price_1 en 0) y en el POS el
    // cajero escribe el total de la venta directamente (ver PosPage). No
    // tiene sentido llevar inventario en kilos si nunca se registra un peso
    // exacto, asi que fuerza tracksInventory a false (ver saveProduct()).
    public bool $flexiblePrice = false;
    public bool $tracksInventory = true;
    public string $minimumStock = '0';
    public array $initialQuantities = [];
    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $filterBrandId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        if (! in_array($status, ['all', 'active', 'inactive', 'archived'], true)) {
            return;
        }

        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function updatedFilterBrandId(): void
    {
        $this->resetPage();
    }

    // Ademas de Enter (wire:keydown.enter en la vista), la busqueda se
    // dispara sola cuando el codigo termina de sincronizarse (wire:model.live
    // .debounce) — asi funciona igual con un lector laser que no manda Enter,
    // o si el usuario borra y vuelve a escribir el codigo sin darle Enter.
    public function updatedBarcode(): void
    {
        $this->lookupBarcode();
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

    // Vista previa en vivo de las barras mientras se escribe/escanea, para
    // confirmar visualmente el codigo antes de guardar. Cualquier valor que
    // el generador no pueda codificar simplemente no muestra preview (no es
    // un error de validacion del producto).
    public function barcodePreviewSvg(): ?string
    {
        $barcode = trim($this->barcode);

        if ($barcode === '') {
            return null;
        }

        try {
            return (new BarcodeGeneratorSVG())->getBarcode($barcode, BarcodeGeneratorSVG::TYPE_CODE_128, 1.4, 40);
        } catch (Throwable) {
            return null;
        }
    }

    public function openModal(): void
    {
        // Si habia un borrador de producto NUEVO sin guardar (el modal se
        // cerro sin querer via dismissModal(), p. ej. clic afuera), lo
        // retomamos en vez de perderlo. Si se estaba editando un producto
        // existente, "+" es inequivocamente "producto nuevo" y si descarta
        // ese borrador (reabrir el lapiz del producto lo vuelve a traer
        // igual, sin perder nada, porque ese si viene de la BD).
        if ($this->editingProductId !== null || $this->name === '') {
            $this->resetProductForm();
        }

        $this->showModal = true;
    }

    // Clic afuera del modal: normalmente sin querer, no debe botar lo que ya
    // se escribio. Ver openModal() para donde se retoma el borrador.
    public function dismissModal(): void
    {
        $this->showModal = false;
    }

    public function closeModal(): void
    {
        $this->resetProductForm();
    }

    public function saveProduct(CreateInventoryAdjustment $createInventoryAdjustment): void
    {
        if ($this->editingProductId) {
            $this->authorize('update', $this->productsQuery()->findOrFail($this->editingProductId));
        } else {
            $this->authorize('create', Product::class);
        }

        $company = $this->currentCompany();

        // Si el usuario escribio un nombre que no coincide con ninguna
        // categoria/marca existente, se crea aqui mismo antes de validar, asi
        // el "+ Nueva" aparte deja de ser necesario en el formulario.
        $this->categoryId = $this->resolveCategoryValue($company, $this->categoryId);
        $this->brandId = $this->resolveBrandValue($company, $this->brandId);

        // El input es texto libre (para permitir tanto "5.5" como "5,5"),
        // asi que se normaliza a punto decimal antes de validar como numeric.
        $this->taxRate = str_replace(',', '.', trim($this->taxRate));

        if ($this->taxRate === '') {
            $this->taxRate = '0';
        }

        $validated = $this->validate([
            'categoryId' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'brandId' => [
                'nullable',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'supplierId' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
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
            'taxRate' => ['required', 'numeric', 'min:0'],
            'price1' => ['required', 'numeric', 'min:0'],
            'price2' => ['nullable', 'numeric', 'min:0'],
            'price3' => ['nullable', 'numeric', 'min:0'],
            'flexiblePrice' => ['required', 'boolean'],
            'tracksInventory' => ['required', 'boolean'],
            'minimumStock' => ['required', 'integer', 'min:0'],
            'initialQuantities' => ['nullable', 'array'],
            'initialQuantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $hasInventory = app(\App\Services\Plans\CompanyPlanResolver::class)->hasModule($company, 'inventory');

        if (! $hasInventory) {
            $validated['tracksInventory'] = false;
            $validated['minimumStock']    = 0;
        }

        // Precio flexible es un concepto de tienda/mercado (perecederos a
        // granel con precio que cambia a diario) que no aplica a un menu de
        // restaurante — se fuerza aqui, no solo se oculta en el formulario,
        // para que no dependa de que el cliente mande el campo coherente.
        if ($company->businessType?->code === 'restaurant') {
            $validated['flexiblePrice'] = false;
        }

        // Precio flexible nunca lleva inventario ni precios de catalogo: el
        // total lo escribe el cajero en el POS cada vez, no tiene sentido
        // llevar stock en kilos que nunca se pesan exactamente. Se fuerza
        // aqui (no solo en el formulario) para que no dependa de que el
        // cliente mande los campos coherentes.
        if ($validated['flexiblePrice']) {
            $validated['tracksInventory'] = false;
            $validated['minimumStock'] = 0;
            $validated['price1'] = '0';
            $validated['price2'] = null;
            $validated['price3'] = null;
        }

        // La carga de existencias iniciales por bodega solo aplica al crear
        // el producto (no al editar uno existente, para eso esta el modulo
        // de Ajuste de inventario) y solo si va a llevar inventario.
        $initialQuantities = (! $this->editingProductId && $hasInventory && $validated['tracksInventory'])
            ? collect($validated['initialQuantities'] ?? [])
                ->filter(fn ($quantity) => $quantity !== null && $quantity !== '' && (float) $quantity > 0)
            : collect();

        $payload = [
            'category_id' => $validated['categoryId'],
            'brand_id' => $validated['brandId'] ?: null,
            'supplier_id' => $validated['supplierId'] ?: null,
            'base_unit_id' => $this->resolveDefaultUnit($company)->id,
            'tax_id' => $this->blankToNull($validated['taxId']),
            'name' => trim($validated['name']),
            'sku' => $this->blankToNull(Str::upper(trim($validated['sku']))),
            'barcode' => $this->blankToNull(trim($validated['barcode'])),
            'description' => $this->blankToNull(trim($validated['description'])),
            'cost' => $validated['cost'],
            'tax_rate' => $validated['taxRate'],
            'price_1' => $validated['price1'],
            'price_2' => $this->blankToNull($validated['price2']),
            'price_3' => $this->blankToNull($validated['price3']),
            'flexible_price' => $validated['flexiblePrice'],
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
            } catch (InvalidArgumentException $exception) {
                $this->addError('name', $exception->getMessage());

                return;
            }

            try {
                DB::transaction(function () use ($company, $payload, $initialQuantities, $createInventoryAdjustment) {
                    $product = Product::query()->create([
                        'company_id' => $company->id,
                        'status' => RecordStatus::Active->value,
                        ...$payload,
                    ]);

                    foreach ($initialQuantities as $warehouseId => $quantity) {
                        $warehouse = Warehouse::query()
                            ->where('company_id', $company->id)
                            ->findOrFail((int) $warehouseId);

                        $createInventoryAdjustment->handle($company, [
                            'branch_id' => $warehouse->branch_id,
                            'warehouse_id' => $warehouse->id,
                            'adjustment_type' => 'increase',
                            'reason' => 'Existencia inicial al crear el producto',
                            'items' => [[
                                'product_id' => $product->id,
                                'quantity' => $quantity,
                                'unit_cost' => $payload['cost'],
                            ]],
                        ]);
                    }
                });
            } catch (InvalidArgumentException $exception) {
                $this->addError('initialQuantities', $exception->getMessage());

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
        $this->supplierId = $product->supplier_id;
        $this->taxId = $product->tax_id ?? '';
        $this->name = $product->name;
        $this->sku = $product->sku ?? '';
        $this->barcode = $product->barcode ?? '';
        $this->description = $product->description ?? '';
        $this->cost   = $this->moneyToString($product->cost);
        $this->taxRate = (string) $product->tax_rate;
        $this->price1 = $this->moneyToString($product->price_1);
        $this->price2 = $product->price_2 !== null ? $this->moneyToString($product->price_2) : '';
        $this->price3 = $product->price_3 !== null ? $this->moneyToString($product->price_3) : '';
        $this->flexiblePrice = $product->flexible_price;
        $this->tracksInventory = $product->tracks_inventory;
        $this->minimumStock = (string) (int) $product->minimum_stock;
        $this->initializeQuantities();
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
            'supplierId',
            'taxId',
            'name',
            'sku',
            'barcode',
            'description',
            'price2',
            'price3'
        );

        $this->cost   = '0';
        $this->taxRate = '0';
        $this->price1 = '0';
        $this->flexiblePrice = false;
        $this->tracksInventory = true;
        $this->minimumStock    = '0';
        $this->initializeQuantities();
        $this->resetValidation();
    }

    public function products(): LengthAwarePaginator
    {
        return $this->productsQuery()
            ->with(['category', 'brand', 'baseUnit'])
            ->when(
                $this->statusFilter === 'archived',
                fn (Builder $query) => $query->onlyTrashed(),
                fn (Builder $query) => $query->whereNull('deleted_at')
            )
            ->when($this->statusFilter === 'active', fn (Builder $query) => $query->where('status', RecordStatus::Active->value))
            ->when($this->statusFilter === 'inactive', fn (Builder $query) => $query->where('status', RecordStatus::Inactive->value))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('name', $search)
                        ->orWhereLike('barcode', $search);
                });
            })
            ->when($this->filterBrandId !== null, fn (Builder $query) => $query->where('brand_id', $this->filterBrandId))
            ->orderBy('name')
            ->paginate($this->perPage);
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

    public function suppliers(): Collection
    {
        return Supplier::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->with('person')
            ->get()
            ->sortBy(fn (Supplier $supplier) => $supplier->person?->full_name ?? '')
            ->values();
    }

    public function warehouses(): Collection
    {
        return Warehouse::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    protected function initializeQuantities(): void
    {
        $this->initialQuantities = $this->warehouses()
            ->mapWithKeys(fn (Warehouse $warehouse) => [$warehouse->id => ''])
            ->all();
    }

    public function render(): View
    {
        $company = $this->currentCompany();

        return view('livewire.products.products-page', [
            'products' => $this->products(),
            'categories' => $this->categories(),
            'brands' => $this->brands(),
            'suppliers' => $this->suppliers(),
            'warehouses' => $this->warehouses(),
            'hasInventory' => app(\App\Services\Plans\CompanyPlanResolver::class)->hasModule($company, 'inventory'),
            'isRestaurant' => $company->businessType?->code === 'restaurant',
            'barcodePreviewSvg' => $this->barcodePreviewSvg(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Productos',
                'description' => 'Define el catalogo base con precios y control inicial de inventario.',
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

    protected function resolveCategoryValue(Company $company, int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return app(ResolveOrCreateCategory::class)->handle($company, (string) $value)->id;
    }

    protected function resolveBrandValue(Company $company, int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return app(ResolveOrCreateBrand::class)->handle($company, (string) $value)->id;
    }

    // La unidad base ya no se elige en el formulario: todo producto usa la
    // unidad generica de la empresa ("Unidad"/UND), creada sola la primera
    // vez que se guarda un producto (no hace falta que el usuario la cree
    // ni que exista de antemano).
    protected function resolveDefaultUnit(Company $company): Unit
    {
        return Unit::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'UND'],
            ['name' => 'Unidad', 'status' => RecordStatus::Active->value]
        );
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
