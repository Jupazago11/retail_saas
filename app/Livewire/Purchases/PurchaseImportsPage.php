<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\ImportPurchaseFromCsv;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class PurchaseImportsPage extends Component
{
    use InteractsWithToast;
    use WithFileUploads;

    public ?int $branchId = null;
    public ?int $warehouseId = null;
    public ?int $supplierId = null;
    public string $supplierName = '';
    public string $invoiceNumber = '';
    public string $purchaseType = 'invoice';
    public string $purchaseStatus = 'confirmed';
    public string $purchasedAt = '';
    public string $dueAt = '';
    public string $notes = '';
    public string $initialPaidAmount = '';
    public UploadedFile|\Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null $importFile = null;

    public array $summary = [
        'file_name' => null,
        'purchase_id' => null,
        'created_count' => 0,
        'error_count' => 0,
        'errors' => [],
    ];

    public function mount(): void
    {
        $this->ensurePermission('purchases.create');

        abort_unless(
            app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'imports')
            && app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'imports.excel'),
            403,
            'El plan actual no tiene habilitadas las importaciones operativas.'
        );

        $this->branchId = $this->branches()->first()?->id;
        $this->warehouseId = $this->warehouses()->first()?->id;
        $this->purchasedAt = now()->format('Y-m-d\TH:i');
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

    public function import(ImportPurchaseFromCsv $importPurchaseFromCsv): void
    {
        $this->ensurePermission('purchases.create');

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
            'supplierId' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'supplierName' => [
                Rule::requiredIf(! $this->supplierId),
                'nullable',
                'string',
                'max:255',
            ],
            'invoiceNumber' => ['nullable', 'string', 'max:120'],
            'purchaseType' => ['required', 'string', 'max:50'],
            'purchaseStatus' => ['required', Rule::in([
                PurchaseStatus::Draft->value,
                PurchaseStatus::Confirmed->value,
                PurchaseStatus::PartiallyPaid->value,
                PurchaseStatus::Paid->value,
            ])],
            'purchasedAt' => ['nullable', 'date'],
            'dueAt' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'initialPaidAmount' => [
                Rule::requiredIf(in_array($this->purchaseStatus, [
                    PurchaseStatus::PartiallyPaid->value,
                    PurchaseStatus::Paid->value,
                ], true)),
                'nullable',
                'numeric',
                'min:0',
            ],
            'importFile' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
        ]);

        try {
            $this->summary = $importPurchaseFromCsv->handle(
                $company,
                $validated['importFile'],
                [
                    'branch_id' => (int) $validated['branchId'],
                    'warehouse_id' => (int) $validated['warehouseId'],
                    'supplier_id' => $validated['supplierId'] ? (int) $validated['supplierId'] : null,
                    'supplier_name' => $this->blankToNull($validated['supplierName']),
                    'invoice_number' => $this->blankToNull($validated['invoiceNumber']),
                    'purchase_type' => trim($validated['purchaseType']),
                    'status' => $validated['purchaseStatus'],
                    'purchased_at' => $this->normalizeDateTime($validated['purchasedAt'] ?? null),
                    'due_at' => $this->normalizeDateTime($validated['dueAt'] ?? null),
                    'notes' => $this->blankToNull($validated['notes']),
                    'paid_amount' => $validated['initialPaidAmount'] !== '' ? (string) $validated['initialPaidAmount'] : null,
                ],
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('importFile', $exception->getMessage());

            return;
        }

        $this->reset('importFile');
        $this->resetValidation('importFile');

        $type = $this->summary['purchase_id'] ? ($this->summary['error_count'] > 0 ? 'warning' : 'success') : 'warning';

        $this->toast(
            $this->summary['purchase_id']
                ? 'Importacion procesada: '.$this->summary['created_count'].' lineas aplicadas y '.$this->summary['error_count'].' con error.'
                : 'No se pudo crear la compra porque ninguna fila fue valida.',
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

    public function suppliers()
    {
        return Supplier::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->with('person')
            ->orderByDesc('credit_balance')
            ->orderByDesc('id')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.purchases.purchase-imports-page', [
            'branches' => $this->branches(),
            'warehouses' => $this->warehouses(),
            'suppliers' => $this->suppliers(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Importar compras',
                'description' => 'Carga lineas de compra por CSV sobre un solo documento comercial y contable.',
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
