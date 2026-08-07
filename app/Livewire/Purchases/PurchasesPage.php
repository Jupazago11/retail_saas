<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Purchases\ReturnPurchase;
use App\Actions\Purchases\UpdateDraftPurchase;
use App\Enums\PayableMovementType;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PayableMovement;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class PurchasesPage extends Component
{
    use InteractsWithToast;

    public ?int $editingPurchaseId = null;
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
    public string $totalAmount = '';
    public string $initialPaidAmount = '';

    public string $search = '';
    public string $statusFilter = '';
    public ?int $supplierFilterId = null;

    public ?int $payingPurchaseId = null;
    public string $paymentAmount = '';
    public string $paymentReference = '';
    public ?int $expandedLedgerPurchaseId = null;

    public bool $showModal = false;

    public function mount(): void
    {
        $this->ensurePermission('purchases.view');
        $this->resetPurchaseForm();
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

    public function savePurchase(): void
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
            'totalAmount' => ['required', 'numeric', 'min:0.01'],
            'initialPaidAmount' => [
                Rule::requiredIf(in_array($this->purchaseStatus, [
                    PurchaseStatus::PartiallyPaid->value,
                    PurchaseStatus::Paid->value,
                ], true)),
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $payload = [
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
            'total' => (string) $validated['totalAmount'],
        ];

        if (in_array($validated['purchaseStatus'], [
            PurchaseStatus::PartiallyPaid->value,
            PurchaseStatus::Paid->value,
        ], true)) {
            $payload['paid_amount'] = (string) $validated['initialPaidAmount'];
        }

        $wasEditing = $this->editingPurchaseId !== null;
        $createPurchase = app(CreatePurchase::class);
        $updateDraftPurchase = app(UpdateDraftPurchase::class);

        try {
            if ($this->editingPurchaseId) {
                $purchase = $this->purchasesQuery()->with(['payableMovements'])->findOrFail($this->editingPurchaseId);
                $updateDraftPurchase->handle($company, $purchase, $payload);
            } else {
                $createPurchase->handle($company, $payload);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('totalAmount', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        $this->resetPurchaseForm();
        $this->toast($wasEditing ? 'Borrador actualizado correctamente.' : 'Compra guardada correctamente.');
    }

    public function editPurchase(int $purchaseId): void
    {
        $this->ensurePermission('purchases.create');

        $purchase = $this->purchasesQuery()->findOrFail($purchaseId);

        if ($purchase->status !== PurchaseStatus::Draft->value || $purchase->posted_to_inventory_at !== null) {
            $this->toast('Solo se pueden editar compras en borrador.', 'warning');

            return;
        }

        $this->editingPurchaseId = $purchase->id;
        $this->branchId = $purchase->branch_id;
        $this->warehouseId = $purchase->warehouse_id;
        $this->supplierId = $purchase->supplier_id;
        $this->supplierName = $purchase->supplier_id ? '' : ($purchase->supplier_name ?? '');
        $this->invoiceNumber = $purchase->invoice_number ?? '';
        $this->purchaseType = $purchase->purchase_type;
        $this->purchaseStatus = $purchase->status;
        $this->purchasedAt = $purchase->purchased_at?->format('Y-m-d') ?? '';
        $this->dueAt = $purchase->due_at?->format('Y-m-d') ?? '';
        $this->notes = $purchase->notes ?? '';
        $this->totalAmount = number_format((float) $purchase->total, 2, '.', '');
        $this->initialPaidAmount = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function startRegisteringPayment(int $purchaseId): void
    {
        $this->ensurePermission('payables.manage');

        $purchase = $this->purchasesQuery()->findOrFail($purchaseId);

        if ((float) $purchase->balance_due <= 0) {
            $this->toast('La compra ya no tiene saldo pendiente.', 'warning');

            return;
        }

        $this->payingPurchaseId = $purchase->id;
        $this->paymentAmount = (string) $purchase->balance_due;
        $this->paymentReference = '';
        $this->resetValidation();
    }

    public function cancelRegisteringPayment(): void
    {
        $this->resetPaymentForm();
    }

    public function openModal(): void
    {
        $this->resetPurchaseForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetPurchaseForm();
    }

    public function toggleLedger(int $purchaseId): void
    {
        $this->expandedLedgerPurchaseId = $this->expandedLedgerPurchaseId === $purchaseId
            ? null
            : $purchaseId;
    }

    public function registerPayment(RegisterPurchasePayment $registerPurchasePayment): void
    {
        $this->ensurePermission('payables.manage');

        $validated = $this->validate([
            'payingPurchaseId' => [
                'required',
                Rule::exists('purchases', 'id')->where(fn ($query) => $query->where('company_id', $this->currentCompany()->id)),
            ],
            'paymentAmount' => ['required', 'numeric', 'gt:0'],
            'paymentReference' => ['nullable', 'string', 'max:120'],
        ]);

        $purchase = $this->purchasesQuery()->findOrFail((int) $validated['payingPurchaseId']);

        try {
            $registerPurchasePayment->handle($this->currentCompany(), $purchase, [
                'amount' => $validated['paymentAmount'],
                'reference' => $this->blankToNull($validated['paymentReference']),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('paymentAmount', $exception->getMessage());

            return;
        }

        $this->resetPaymentForm();
        $this->toast('Pago registrado correctamente.');
    }

    public function returnPurchase(int $purchaseId, ReturnPurchase $returnPurchase): void
    {
        $this->ensurePermission('purchases.create');

        $purchase = $this->purchasesQuery()->findOrFail($purchaseId);

        try {
            $returnPurchase->handle($this->currentCompany(), $purchase);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'warning');

            return;
        }

        $this->toast('Compra devuelta correctamente.', 'info');
    }

    public function branches(): Collection
    {
        return Branch::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function warehouses(): Collection
    {
        if (! $this->branchId) {
            return new Collection();
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

    public function suppliers(): Collection
    {
        return Supplier::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->with('person')
            ->orderByDesc('credit_balance')
            ->orderByDesc('id')
            ->get();
    }

    public function purchases(): Collection
    {
        return $this->purchasesQuery()
            ->with([
                'branch',
                'warehouse',
                'supplier.person',
                'payableMovements.supplier.person',
            ])
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->supplierFilterId, fn (Builder $query) => $query->where('supplier_id', $this->supplierFilterId))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%' . trim($this->search) . '%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('invoice_number', $search)
                        ->orWhereLike('supplier_name', $search)
                        ->orWhereLike('purchase_type', $search)
                        ->orWhereHas('supplier.person', function (Builder $personQuery) use ($search) {
                            $personQuery
                                ->whereLike('first_name', $search)
                                ->orWhereLike('last_name', $search)
                                ->orWhereLike('document_number', $search);
                        });
                });
            })
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->get();
    }

    public function canCreatePurchases(): bool
    {
        return $this->branches()->isNotEmpty()
            && $this->warehouses()->isNotEmpty();
    }

    public function canManagePayables(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('payables.manage') ?? false;
    }

    public function movementLabel(PayableMovement $movement): string
    {
        return match ($movement->movement_type) {
            PayableMovementType::PurchaseCharge->value => 'Cargo inicial',
            PayableMovementType::Payment->value => 'Pago',
            PayableMovementType::PurchaseReturnAdjustment->value => 'Ajuste por devolucion',
            PayableMovementType::SupplierCreditGenerated->value => 'Saldo a favor generado',
            PayableMovementType::SupplierCreditApplied->value => 'Saldo a favor aplicado',
            default => $movement->movement_type,
        };
    }

    public function movementBadgeClass(PayableMovement $movement): string
    {
        return match ($movement->movement_type) {
            PayableMovementType::PurchaseCharge->value => 'bg-amber-100 text-amber-700',
            PayableMovementType::Payment->value => 'bg-emerald-100 text-emerald-700',
            PayableMovementType::PurchaseReturnAdjustment->value => 'bg-rose-100 text-rose-700',
            PayableMovementType::SupplierCreditGenerated->value => 'bg-sky-100 text-sky-700',
            PayableMovementType::SupplierCreditApplied->value => 'bg-violet-100 text-violet-700',
            default => 'bg-stone-200 text-stone-700',
        };
    }

    public function render(): View
    {
        return view('livewire.purchases.purchases-page', [
            'branches' => $this->branches(),
            'warehouses' => $this->warehouses(),
            'suppliers' => $this->suppliers(),
            'purchases' => $this->purchases(),
            'canCreatePurchases' => $this->canCreatePurchases(),
            'canManagePayables' => $this->canManagePayables(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Compras',
                'description' => 'Registra compras, consolida lineas de ingreso y controla pagos posteriores sobre el mismo documento.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function purchasesQuery()
    {
        return Purchase::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }

    protected function resetPurchaseForm(): void
    {
        $this->editingPurchaseId = null;
        $branches = $this->branches();
        $this->branchId = $branches->first()?->id;
        $this->warehouseId = $this->warehouses()->first()?->id;
        $this->supplierId = null;
        $this->supplierName = '';
        $this->invoiceNumber = '';
        $this->purchaseType = 'invoice';
        $this->purchaseStatus = PurchaseStatus::Confirmed->value;
        $this->purchasedAt = now()->format('Y-m-d');
        $this->dueAt = '';
        $this->notes = '';
        $this->totalAmount = '';
        $this->initialPaidAmount = '';
        $this->resetValidation();
    }

    protected function resetPaymentForm(): void
    {
        $this->reset('payingPurchaseId', 'paymentAmount', 'paymentReference');
        $this->resetValidation();
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
