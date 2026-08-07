<?php

namespace App\Livewire\Inventory;

use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\Company;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryAdjustmentPage extends Component
{
    use InteractsWithToast, WithPagination;

    public string $search = '';
    public ?int $branchId = null;
    public ?int $warehouseId = null;

    public ?int $adjustingProductId = null;
    public string $adjustmentType = 'increase';
    public string $quantity = '';
    public string $unitCost = '';
    public string $reason = '';
    public string $notes = '';

    public function mount(): void
    {
        $this->ensurePermission('inventory.adjust');
        $branch = $this->branches()->first();
        $this->branchId = $branch?->id;
        $this->warehouseId = $this->warehouses()->first()?->id;
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
            ->paginate(15);
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
        return view('livewire.inventory.inventory-adjustment-page', [
            'products'   => $this->products(),
            'branches'   => $this->branches(),
            'warehouses' => $this->warehouses(),
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
