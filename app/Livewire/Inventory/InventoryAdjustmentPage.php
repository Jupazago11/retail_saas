<?php

namespace App\Livewire\Inventory;

use App\Actions\Inventory\BulkSetupProductInventory;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\HasResponsivePageSize;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryAdjustmentPage extends Component
{
    use HasResponsivePageSize, InteractsWithToast, WithPagination;

    public int $perPage = 15;

    public string $search = '';
    public ?int $branchId = null;
    public ?int $warehouseId = null;

    public ?int $adjustingProductId = null;
    public string $adjustmentType = 'increase';
    public string $quantity = '';
    public string $unitCost = '';
    public string $reason = '';
    public string $notes = '';

    public string $step = 'adjust';
    public bool $wizardReentryMode = false;
    public ?string $wizardSupplierKey = null;
    public array $wizardRows = [];

    public function mount(): void
    {
        $this->ensurePermission('inventory.adjust');
        $branch = $this->branches()->first();
        $this->branchId = $branch?->id;
        $this->warehouseId = $this->warehouses()->first()?->id;

        $company = $this->currentCompany();
        $hasInventory = app(CompanyPlanResolver::class)->hasModule($company, 'inventory');
        $alreadyAnswered = (bool) app(CompanySettings::class)->get($company, 'inventory', 'setup_wizard_answered');

        if ($hasInventory && ! $alreadyAnswered) {
            $this->step = 'gate';
        }
    }

    public function answerWizardGate(bool $useInventory, CompanySettings $companySettings): void
    {
        $company = $this->currentCompany();
        $companySettings->set($company, 'inventory', 'setup_wizard_answered', true);

        if (! $useInventory) {
            $this->step = 'adjust';

            return;
        }

        $this->wizardReentryMode = false;
        $this->openWizardAtFirstSupplier();
    }

    public function openReentryWizard(): void
    {
        $this->wizardReentryMode = true;
        $this->openWizardAtFirstSupplier();
    }

    public function exitWizard(): void
    {
        $this->step = 'adjust';
        $this->wizardSupplierKey = null;
        $this->wizardRows = [];
    }

    public function selectWizardSupplier(string $key): void
    {
        $this->wizardSupplierKey = $key;
        $this->initializeWizardRows();
    }

    public function nextWizardSupplier(): void
    {
        $this->stepWizardSupplier(1);
    }

    public function prevWizardSupplier(): void
    {
        $this->stepWizardSupplier(-1);
    }

    public function saveWizardSupplier(BulkSetupProductInventory $bulkSetupProductInventory): void
    {
        $this->ensurePermission('inventory.adjust');

        $company = $this->currentCompany();
        $warehouseIds = $this->wizardWarehouses()->pluck('id')->all();

        $validated = $this->validate([
            'wizardRows.*.tracks_inventory' => ['required', 'boolean'],
            'wizardRows.*.minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'wizardRows.*.quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rows = collect($validated['wizardRows'] ?? [])
            ->map(function (array $row, int $productId) use ($warehouseIds) {
                $quantities = [];

                foreach ($warehouseIds as $warehouseId) {
                    $quantities[$warehouseId] = $row['quantities'][$warehouseId] ?? '0';
                }

                return [
                    'product_id' => $productId,
                    'tracks_inventory' => (bool) $row['tracks_inventory'],
                    'minimum_stock' => $row['minimum_stock'] ?: '0',
                    'quantities' => $quantities,
                ];
            })
            ->values()
            ->all();

        try {
            $bulkSetupProductInventory->handle($company, $rows);
        } catch (InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->toast('Productos configurados correctamente.');

        $suppliers = $this->wizardSuppliers();
        $currentIndex = $suppliers->search(fn (array $s) => $s['key'] === $this->wizardSupplierKey);
        $next = $currentIndex === false ? null : $suppliers->get($currentIndex + 1);

        if ($next) {
            $this->selectWizardSupplier($next['key']);
        } else {
            $this->exitWizard();
        }
    }

    protected function openWizardAtFirstSupplier(): void
    {
        $this->step = 'wizard';
        $first = $this->wizardSuppliers()->first();

        if (! $first) {
            $this->wizardSupplierKey = null;
            $this->wizardRows = [];

            return;
        }

        $this->selectWizardSupplier($first['key']);
    }

    protected function stepWizardSupplier(int $direction): void
    {
        $suppliers = $this->wizardSuppliers();
        $currentIndex = $suppliers->search(fn (array $s) => $s['key'] === $this->wizardSupplierKey);

        if ($currentIndex === false) {
            return;
        }

        $target = $suppliers->get($currentIndex + $direction);

        if ($target) {
            $this->selectWizardSupplier($target['key']);
        }
    }

    protected function initializeWizardRows(): void
    {
        $warehouses = $this->wizardWarehouses();
        $rows = [];

        foreach ($this->wizardSupplierProducts() as $product) {
            $quantities = [];

            foreach ($warehouses as $warehouse) {
                $quantities[$warehouse->id] = '';
            }

            $rows[$product->id] = [
                'tracks_inventory' => true,
                'minimum_stock' => $product->minimum_stock > 0 ? (string) $product->minimum_stock : '0',
                'quantities' => $quantities,
            ];
        }

        $this->wizardRows = $rows;
        $this->resetValidation();
    }

    public function wizardSupplierProducts(): Collection
    {
        if ($this->wizardSupplierKey === null) {
            return new Collection();
        }

        return $this->baseWizardProductsQuery()
            ->where(function (Builder $q) {
                if ($this->wizardSupplierKey === 'none') {
                    $q->whereNull('supplier_id');
                } else {
                    $q->where('supplier_id', (int) $this->wizardSupplierKey);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function wizardSuppliers(): \Illuminate\Support\Collection
    {
        $counts = $this->baseWizardProductsQuery()
            ->selectRaw('supplier_id, count(*) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');

        if ($counts->isEmpty()) {
            return collect();
        }

        $company = $this->currentCompany();
        $suppliers = Supplier::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $counts->keys()->filter()->all())
            ->with('person')
            ->get()
            ->sortBy(fn (Supplier $supplier) => $supplier->person?->full_name ?? '');

        $entries = $suppliers->map(fn (Supplier $supplier) => [
            'key' => (string) $supplier->id,
            'label' => $supplier->person?->full_name ?: 'Sin nombre',
            'count' => (int) ($counts[$supplier->id] ?? 0),
        ])->values();

        // pluck() castea la clave supplier_id=null a '' (comportamiento de
        // PHP al usar null como indice de arreglo), por eso se compara con
        // la cadena vacia y no con null.
        if ($counts->has('')) {
            $entries->push([
                'key' => 'none',
                'label' => 'Sin proveedor',
                'count' => (int) $counts->get(''),
            ]);
        }

        return $entries;
    }

    protected function baseWizardProductsQuery(): Builder
    {
        $company = $this->currentCompany();

        return Product::query()
            ->where('company_id', $company->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->when($this->wizardReentryMode, fn (Builder $q) => $q->where('tracks_inventory', false));
    }

    public function wizardWarehouses(): \Illuminate\Support\Collection
    {
        $company = $this->currentCompany();

        return Warehouse::query()
            ->where('company_id', $company->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->with('branch')
            ->orderBy('name')
            ->get();
    }

    public function updatedBranchId(): void
    {
        $this->warehouseId = $this->warehouses()->first()?->id;
        $this->resetPage();
        $this->closeAdjustForm();
    }

    public function updatedWarehouseId(): void
    {
        $this->resetPage();
        $this->closeAdjustForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openAdjustForm(int $productId): void
    {
        $this->adjustingProductId = $productId;
        $this->adjustmentType = 'increase';
        $this->quantity = '';
        $this->unitCost = '';
        $this->reason = '';
        $this->notes = '';
        $this->resetValidation();
    }

    public function closeAdjustForm(): void
    {
        $this->adjustingProductId = null;
        $this->resetValidation();
    }

    public function saveAdjustment(CreateInventoryAdjustment $createInventoryAdjustment): void
    {
        $this->ensurePermission('inventory.adjust');

        $company = $this->currentCompany();

        $validated = $this->validate([
            'branchId'       => ['required', Rule::exists('branches', 'id')->where('company_id', $company->id)],
            'warehouseId'    => ['required', Rule::exists('warehouses', 'id')->where('company_id', $company->id)],
            'adjustingProductId' => ['required', Rule::exists('products', 'id')->where('company_id', $company->id)],
            'adjustmentType' => ['required', Rule::in(['increase', 'decrease'])],
            'quantity'       => ['required', 'numeric', 'gt:0'],
            'unitCost'       => ['required_if:adjustmentType,increase', 'nullable', 'numeric', 'min:0'],
            'reason'         => ['required', 'string', 'max:255'],
            'notes'          => ['nullable', 'string'],
        ]);

        try {
            $createInventoryAdjustment->handle($company, [
                'branch_id'       => (int) $validated['branchId'],
                'warehouse_id'    => (int) $validated['warehouseId'],
                'adjustment_type' => $validated['adjustmentType'],
                'reason'          => $validated['reason'],
                'notes'           => $this->blankToNull($validated['notes']),
                'adjusted_at'     => now()->format('Y-m-d H:i:s'),
                'items' => [[
                    'product_id'         => (int) $validated['adjustingProductId'],
                    'product_variant_id' => null,
                    'quantity'           => $validated['quantity'],
                    'unit_cost'          => $validated['unitCost'] ?? '0',
                ]],
            ]);
        } catch (InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'error');
            return;
        }

        $this->closeAdjustForm();
        $this->toast('Ajuste registrado correctamente.');
    }

    public function products(): LengthAwarePaginator
    {
        return Product::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->where('tracks_inventory', true)
            ->whereNull('deleted_at')
            ->with(['category', 'baseUnit'])
            ->when($this->warehouseId, function (Builder $q) {
                $q->with(['inventoryBalances' => function ($q2) {
                    $q2->where('warehouse_id', $this->warehouseId);
                }]);
            })
            ->when($this->search !== '', function (Builder $q) {
                $s = '%' . trim($this->search) . '%';
                $q->where(fn (Builder $n) => $n->whereLike('name', $s)->orWhereLike('barcode', $s));
            })
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function branches()
    {
        return Branch::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function warehouses()
    {
        if (! $this->branchId) {
            return collect();
        }
        return Warehouse::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        $company = $this->currentCompany();

        return view('livewire.inventory.inventory-adjustment-page', [
            'products'   => $this->step === 'adjust' ? $this->products() : null,
            'branches'   => $this->branches(),
            'warehouses' => $this->warehouses(),
            'hasInventoryModule' => app(CompanyPlanResolver::class)->hasModule($company, 'inventory'),
            'hasImportsFeature' => app(CompanyPlanResolver::class)->hasModule($company, 'imports')
                && app(CompanyPlanResolver::class)->hasFeature($company, 'imports.excel'),
            'wizardSuppliers' => in_array($this->step, ['wizard'], true) ? $this->wizardSuppliers() : collect(),
            'wizardWarehouses' => in_array($this->step, ['wizard'], true) ? $this->wizardWarehouses() : collect(),
            'wizardProducts' => in_array($this->step, ['wizard'], true) ? $this->wizardSupplierProducts() : new Collection(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title'       => 'Ajuste de inventario',
                'description' => 'Registra entradas y salidas manuales de stock por producto.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function ensurePermission(string $code): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($code),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }

    protected function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
