<?php

namespace App\Livewire\Inventory;

use App\Actions\Inventory\ImportInventoryAdjustmentFromCsv;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Warehouse;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class InventoryAdjustmentImportsPage extends Component
{
    use InteractsWithToast;
    use WithFileUploads;

    public ?int $branchId = null;
    public ?int $warehouseId = null;
    public string $adjustmentType = 'increase';
    public string $reason = '';
    public string $notes = '';
    public string $adjustedAt = '';
    public UploadedFile|\Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null $importFile = null;

    public array $summary = [
        'file_name' => null,
        'adjustment_id' => null,
        'created_count' => 0,
        'error_count' => 0,
        'errors' => [],
    ];

    public function mount(): void
    {
        $this->ensurePermission('inventory.adjust');

        abort_unless(
            app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'imports')
            && app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'imports.excel'),
            403,
            'El plan actual no tiene habilitadas las importaciones operativas.'
        );

        $this->branchId = $this->branches()->first()?->id;
        $this->warehouseId = $this->warehouses()->first()?->id;
        $this->adjustedAt = now()->format('Y-m-d\TH:i');
    }

    public function updatedBranchId($value): void
    {
        $value = $value ? (int) $value : null;
        $warehouseIds = $this->warehouses()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($value === null || ! in_array((int) $this->warehouseId, $warehouseIds, true)) {
            $this->warehouseId = $warehouseIds[0] ?? null;
        }
    }

    public function import(ImportInventoryAdjustmentFromCsv $importInventoryAdjustmentFromCsv): void
    {
        $this->ensurePermission('inventory.adjust');

        $company = $this->currentCompany();

        $validated = $this->validate([
            'branchId' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'warehouseId' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('branch_id', $this->branchId)
                    ->whereNull('deleted_at')),
            ],
            'adjustmentType' => ['required', Rule::in([
                InventoryAdjustmentType::Increase->value,
                InventoryAdjustmentType::Decrease->value,
            ])],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'adjustedAt' => ['nullable', 'date'],
            'importFile' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
        ]);

        try {
            $this->summary = $importInventoryAdjustmentFromCsv->handle(
                $company,
                $validated['importFile'],
                [
                    'branch_id' => (int) $validated['branchId'],
                    'warehouse_id' => (int) $validated['warehouseId'],
                    'adjustment_type' => $validated['adjustmentType'],
                    'reason' => trim($validated['reason']),
                    'notes' => $this->blankToNull($validated['notes'] ?? null),
                    'adjusted_at' => $this->normalizeDateTime($validated['adjustedAt'] ?? null),
                ],
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('importFile', $exception->getMessage());

            return;
        }

        $this->reset('importFile');
        $this->resetValidation('importFile');

        $type = $this->summary['adjustment_id'] ? ($this->summary['error_count'] > 0 ? 'warning' : 'success') : 'warning';

        $this->toast(
            $this->summary['adjustment_id']
                ? 'Importacion procesada: '.$this->summary['created_count'].' lineas aplicadas y '.$this->summary['error_count'].' con error.'
                : 'No se pudo crear el ajuste porque ninguna fila fue valida.',
            $type
        );
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
        return view('livewire.inventory.inventory-adjustment-imports-page', [
            'branches' => $this->branches(),
            'warehouses' => $this->warehouses(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Importar ajustes de inventario',
                'description' => 'Carga lineas de ajuste por CSV sobre una sucursal y bodega definidas en el formulario.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeDateTime(mixed $value): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}
