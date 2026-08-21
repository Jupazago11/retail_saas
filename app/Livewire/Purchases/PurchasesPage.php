<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Purchases\ReturnPurchase;
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

    public ?int $branchId = null;
    public ?int $warehouseId = null;
    public ?int $supplierId = null;
    public string $supplierName = '';
    public string $invoiceNumber = '';
    public string $purchaseType = 'invoice';
    public string $purchasedAt = '';
    public string $dueAt = '';
    public string $notes = '';
    public string $totalAmount = '';

    public string $search = '';
    public string $statusFilter = 'confirmed';
    public ?int $supplierFilterId = null;

    public string $paymentAmount = '';
    public string $paymentReference = '';
    public ?int $ledgerPurchaseId = null;

    public bool $showModal = false;

    public function mount(): void
    {
        $this->ensurePermission('purchases.view');
        $this->resetPurchaseForm();
    }

    public function setStatusFilter(string $status): void
    {
        if (! in_array($status, ['', 'confirmed', 'partially_paid', 'paid', 'cancelled', 'archived'], true)) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function archivePurchase(int $purchaseId): void
    {
        $this->ensurePermission('purchases.create');

        $purchase = $this->purchasesQuery()->findOrFail($purchaseId);

        if ((float) $purchase->balance_due > 0) {
            $this->toast('No puedes archivar una compra con saldo pendiente. Registra el pago o la devolucion primero.', 'warning');

            return;
        }

        $purchase->delete();
        $this->toast('Compra archivada correctamente.', 'warning');
    }

    public function restorePurchase(int $purchaseId): void
    {
        $this->ensurePermission('purchases.create');

        $purchase = $this->purchasesQuery()->onlyTrashed()->findOrFail($purchaseId);
        $purchase->restore();

        $this->toast('Compra restaurada correctamente.');
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
            'purchasedAt' => ['nullable', 'date'],
            'dueAt' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'totalAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $payload = [
            'branch_id' => (int) $validated['branchId'],
            'warehouse_id' => (int) $validated['warehouseId'],
            'supplier_id' => $validated['supplierId'] ? (int) $validated['supplierId'] : null,
            'supplier_name' => $this->blankToNull($validated['supplierName']),
            'invoice_number' => $this->blankToNull($validated['invoiceNumber']),
            'purchase_type' => trim($validated['purchaseType']),
            // Toda compra nueva nace en Pendiente; el estado avanza despues via pagos reales.
            'status' => PurchaseStatus::Confirmed->value,
            'purchased_at' => $this->normalizeDateTime($validated['purchasedAt'] ?? null),
            'due_at' => $this->normalizeDateTime($validated['dueAt'] ?? null),
            'notes' => $this->blankToNull($validated['notes']),
            'total' => (string) $validated['totalAmount'],
        ];

        try {
            app(CreatePurchase::class)->handle($company, $payload);
        } catch (InvalidArgumentException $exception) {
            $this->addError('totalAmount', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        $this->resetPurchaseForm();
        $this->toast('Compra guardada correctamente.');
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

    // Abre el modal de movimientos de la compra. Si el usuario puede
    // gestionar pagos y la compra todavia tiene saldo, tambien deja lista la
    // pestana de "Registrar pago" que vive dentro del mismo modal (ya no hay
    // una fila aparte en la tabla para eso).
    public function openLedger(int $purchaseId): void
    {
        $purchase = $this->purchasesQuery()->findOrFail($purchaseId);

        $this->ledgerPurchaseId = $purchase->id;
        $this->resetPaymentForm();

        if ($this->canManagePayables() && in_array($purchase->status, ['confirmed', 'partially_paid'], true) && (float) $purchase->balance_due > 0) {
            $this->paymentAmount = (string) (int) round((float) $purchase->balance_due);
        }
    }

    public function closeLedger(): void
    {
        $this->ledgerPurchaseId = null;
        $this->resetPaymentForm();
    }

    public function registerPayment(RegisterPurchasePayment $registerPurchasePayment): void
    {
        $this->ensurePermission('payables.manage');

        $validated = $this->validate([
            'ledgerPurchaseId' => [
                'required',
                Rule::exists('purchases', 'id')->where(fn ($query) => $query->where('company_id', $this->currentCompany()->id)),
            ],
            'paymentAmount' => ['required', 'numeric', 'gt:0'],
            'paymentReference' => ['nullable', 'string', 'max:120'],
        ]);

        $purchase = $this->purchasesQuery()->findOrFail((int) $validated['ledgerPurchaseId']);

        try {
            $registerPurchasePayment->handle($this->currentCompany(), $purchase, [
                'amount' => $validated['paymentAmount'],
                'reference' => $this->blankToNull($validated['paymentReference']),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('paymentAmount', $exception->getMessage());

            return;
        }

        // El modal se queda abierto (no se cierra el ledger) para que se vea
        // de una vez el movimiento de pago recien creado.
        $this->paymentAmount = '';
        $this->paymentReference = '';
        $this->resetValidation();
        $this->toast('Pago registrado correctamente.');
    }

    public function cancelPurchase(int $purchaseId, ReturnPurchase $returnPurchase): void
    {
        $this->ensurePermission('purchases.create');

        $purchase = $this->purchasesQuery()->findOrFail($purchaseId);

        try {
            $returnPurchase->handle($this->currentCompany(), $purchase);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'warning');

            return;
        }

        $this->toast('Compra cancelada correctamente.', 'info');
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
            ->when($this->statusFilter === 'archived', fn (Builder $query) => $query->onlyTrashed())
            ->when(
                $this->statusFilter !== '' && $this->statusFilter !== 'archived',
                fn (Builder $query) => $query->where('status', $this->statusFilter)
            )
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

    // Se resuelve aparte de purchases() (que ya viene paginada/filtrada por
    // los filtros de la lista) para que el modal siempre muestre el ledger
    // al dia, incluyendo justo despues de registrar un pago.
    public function ledgerPurchase(): ?Purchase
    {
        if (! $this->ledgerPurchaseId) {
            return null;
        }

        return $this->purchasesQuery()
            ->with(['payableMovements.supplier.person'])
            ->find($this->ledgerPurchaseId);
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

    /**
     * Semaforo de vencimiento para la fila de una compra: que tan cerca
     * esta el plazo de pago del proveedor. Null cuando no aplica (sin
     * fecha limite, ya pagada/cancelada, o sin saldo pendiente) — esas
     * filas no necesitan alerta.
     *
     * @return array{color: string, label: string}|null
     */
    public function dueStatus(Purchase $purchase): ?array
    {
        if (
            $purchase->due_at === null
            || in_array($purchase->status, [PurchaseStatus::Paid->value, PurchaseStatus::Cancelled->value], true)
            || (float) $purchase->balance_due <= 0
        ) {
            return null;
        }

        $today = now()->startOfDay();
        $dueDate = $purchase->due_at->copy()->startOfDay();
        // diffInDays() ya devuelve signo (negativo si $dueDate quedo antes
        // de hoy) en esta version de Carbon — no hace falta corregir signo
        // a mano (hacerlo duplica la negacion y da el resultado invertido).
        $daysUntilDue = (int) $today->diffInDays($dueDate);

        if ($daysUntilDue < 0) {
            $daysOverdue = abs($daysUntilDue);

            return [
                'color' => 'rose',
                'label' => 'Vencida hace '.$daysOverdue.' '.\Illuminate\Support\Str::plural('dia', $daysOverdue),
            ];
        }

        if ($daysUntilDue === 0) {
            return ['color' => 'rose', 'label' => 'Vence hoy'];
        }

        if ($daysUntilDue <= 7) {
            return ['color' => 'amber', 'label' => 'Vence en '.$daysUntilDue.' '.\Illuminate\Support\Str::plural('dia', $daysUntilDue)];
        }

        return ['color' => 'emerald', 'label' => 'Vence en '.$daysUntilDue.' dias'];
    }

    /**
     * Para un movimiento de tipo Pago: si la compra tiene fecha limite,
     * indica si ese pago llego a tiempo, con cuantos dias de anticipacion o
     * con cuantos dias de retraso. Null cuando no aplica (no es un pago, o
     * la compra no tiene fecha limite registrada).
     *
     * @return array{color: string, label: string}|null
     */
    public function paymentTimeliness(PayableMovement $movement, Purchase $purchase): ?array
    {
        if ($movement->movement_type !== PayableMovementType::Payment->value) {
            return null;
        }

        if ($purchase->due_at === null || $movement->occurred_at === null) {
            return null;
        }

        $dueDate = $purchase->due_at->copy()->startOfDay();
        $paidDate = $movement->occurred_at->copy()->startOfDay();
        // Mismo criterio de signo que dueStatus(): negativo si $paidDate
        // quedo antes de $dueDate (pago adelantado).
        $daysFromDue = (int) $dueDate->diffInDays($paidDate);

        if ($daysFromDue < 0) {
            $daysEarly = abs($daysFromDue);

            return [
                'color' => 'emerald',
                'label' => 'Pagado con '.$daysEarly.' '.\Illuminate\Support\Str::plural('dia', $daysEarly).' de anticipacion',
            ];
        }

        if ($daysFromDue === 0) {
            return ['color' => 'emerald', 'label' => 'Pagado a tiempo'];
        }

        return [
            'color' => 'rose',
            'label' => 'Pagado con '.$daysFromDue.' '.\Illuminate\Support\Str::plural('dia', $daysFromDue).' de retraso',
        ];
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
            'ledgerPurchase' => $this->ledgerPurchase(),
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
        $branches = $this->branches();
        $this->branchId = $branches->first()?->id;
        $this->warehouseId = $this->warehouses()->first()?->id;
        $this->supplierId = null;
        $this->supplierName = '';
        $this->invoiceNumber = '';
        $this->purchaseType = 'invoice';
        $this->purchasedAt = now()->format('Y-m-d');
        $this->dueAt = '';
        $this->notes = '';
        $this->totalAmount = '';
        $this->resetValidation();
    }

    protected function resetPaymentForm(): void
    {
        $this->reset('paymentAmount', 'paymentReference');
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
