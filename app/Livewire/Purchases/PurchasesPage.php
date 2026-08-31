<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Purchases\ReturnPurchase;
use App\Actions\Suppliers\CreateSupplier;
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

    // Nullable a proposito: null = todavia no responde la pregunta (asi el
    // resto del formulario se queda oculto hasta que elige), a diferencia
    // de false que significa "respondio que no".
    public ?bool $paidImmediately = null;
    public string $paymentCompletionMode = 'full';
    public string $initialPaidAmount = '';
    public string $paymentMethodMode = 'cash';
    public string $mixedCashAmount = '';
    public string $mixedTransferAmount = '';

    public string $search = '';
    public string $statusFilter = '';
    public ?int $supplierFilterId = null;

    public string $paymentAmount = '';
    public string $paymentReference = '';
    public string $paymentMethodCode = 'cash';
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
            'paidImmediately' => ['required', 'boolean'],
            'paymentCompletionMode' => [Rule::in(['full', 'partial'])],
            'paymentMethodMode' => [Rule::in(['cash', 'transfer', 'mixed'])],
            'initialPaidAmount' => [
                Rule::requiredIf(fn () => $this->paidImmediately && $this->paymentCompletionMode === 'partial'),
                'nullable', 'numeric', 'gt:0',
            ],
            'mixedCashAmount' => [
                Rule::requiredIf(fn () => $this->paidImmediately && $this->paymentMethodMode === 'mixed'),
                'nullable', 'numeric', 'min:0',
            ],
            'mixedTransferAmount' => [
                Rule::requiredIf(fn () => $this->paidImmediately && $this->paymentMethodMode === 'mixed'),
                'nullable', 'numeric', 'min:0',
            ],
        ]);

        $supplierId = $validated['supplierId'] ? (int) $validated['supplierId'] : null;
        $typedSupplierName = $this->blankToNull($validated['supplierName']);

        // Igual que con clientes en el POS: si escribiste un nombre que no
        // seleccionaste de la lista, se crea el proveedor de una vez (no se
        // guarda solo como texto suelto) para que quede en Proveedores y le
        // puedas agregar documento, telefono, plazo, etc. despues.
        if ($supplierId === null && $typedSupplierName !== null) {
            try {
                $supplierId = $this->resolveOrCreateSupplierByName($company, $typedSupplierName)->id;
            } catch (InvalidArgumentException $exception) {
                $this->addError('supplierName', $exception->getMessage());

                return;
            }
        }

        // Por defecto la compra nace Pendiente y "Vence" aplica normal. Si
        // ya se pago (total o parcial) al momento de registrarla, no hay
        // fecha de vencimiento que pedir y el pago inicial se registra de
        // una vez, con su medio de pago (o repartido si fue mixto).
        $requestedStatus = PurchaseStatus::Confirmed->value;
        $dueAtValue = $this->normalizeDateTime($validated['dueAt'] ?? null);
        $skipDefaultDueAt = false;
        $initialPayments = null;

        if ($this->paidImmediately) {
            $skipDefaultDueAt = true;
            $dueAtValue = null;

            $totalAmount = (string) $validated['totalAmount'];
            $paidAmount = $this->paymentCompletionMode === 'partial'
                ? (string) $validated['initialPaidAmount']
                : $totalAmount;

            if ($this->paymentCompletionMode === 'partial' && bccomp($paidAmount, $totalAmount, 2) !== -1) {
                $this->addError('initialPaidAmount', 'El pago inicial debe ser menor al monto total (si es el total completo, usa "Completo").');

                return;
            }

            $requestedStatus = $this->paymentCompletionMode === 'partial'
                ? PurchaseStatus::PartiallyPaid->value
                : PurchaseStatus::Paid->value;

            if ($this->paymentMethodMode === 'mixed') {
                $cashAmount = (string) $validated['mixedCashAmount'];
                $transferAmount = (string) $validated['mixedTransferAmount'];

                if (bccomp(bcadd($cashAmount, $transferAmount, 2), $paidAmount, 2) !== 0) {
                    $label = $this->paymentCompletionMode === 'partial' ? 'lo pagado ahora' : 'el monto total';
                    $this->addError('mixedCashAmount', "Efectivo + transferencia debe sumar exactamente {$label}.");

                    return;
                }

                $initialPayments = array_values(array_filter([
                    bccomp($cashAmount, '0.00', 2) === 1 ? ['amount' => $cashAmount, 'payment_method_code' => 'cash'] : null,
                    bccomp($transferAmount, '0.00', 2) === 1 ? ['amount' => $transferAmount, 'payment_method_code' => 'transfer'] : null,
                ]));
            } else {
                $initialPayments = [[
                    'amount' => $paidAmount,
                    'payment_method_code' => $this->paymentMethodMode,
                ]];
            }
        }

        $payload = [
            'branch_id' => (int) $validated['branchId'],
            'warehouse_id' => (int) $validated['warehouseId'],
            'supplier_id' => $supplierId,
            'invoice_number' => $this->blankToNull($validated['invoiceNumber']),
            'purchase_type' => trim($validated['purchaseType']),
            'status' => $requestedStatus,
            'purchased_at' => $this->normalizeDateTime($validated['purchasedAt'] ?? null),
            'due_at' => $dueAtValue,
            'skip_default_due_at' => $skipDefaultDueAt,
            'notes' => $this->blankToNull($validated['notes']),
            'total' => (string) $validated['totalAmount'],
        ];

        if ($initialPayments !== null) {
            $payload['initial_payments'] = $initialPayments;
        }

        try {
            app(CreatePurchase::class)->handle($company, $payload);
        } catch (InvalidArgumentException $exception) {
            $this->addError('totalAmount', $exception->getMessage());

            return;
        }

        // Recuerda el ultimo tipo usado por este usuario — la mayoria de
        // negocios siempre registra el mismo tipo de documento, asi la
        // proxima compra ya arranca con el correcto preseleccionado.
        auth()->user()?->update(['last_purchase_type' => $payload['purchase_type']]);

        $this->showModal = false;
        $this->resetPurchaseForm();
        $this->toast('Compra guardada correctamente.');
    }

    public function openModal(): void
    {
        $this->showModal = true;
    }

    // Cerrar (con la X, clic afuera o "Cancelar") ya NO borra lo que se
    // llevaba escrito — si el cierre fue sin querer, "+" vuelve a abrir el
    // mismo formulario con los datos intactos. El formulario solo se limpia
    // de verdad tras guardar con exito (ver savePurchase()).
    public function closeModal(): void
    {
        $this->showModal = false;
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
            'paymentMethodCode' => ['required', Rule::in(['cash', 'transfer'])],
        ]);

        $purchase = $this->purchasesQuery()->findOrFail((int) $validated['ledgerPurchaseId']);

        try {
            $registerPurchasePayment->handle($this->currentCompany(), $purchase, [
                'amount' => $validated['paymentAmount'],
                'reference' => $this->blankToNull($validated['paymentReference']),
                'payment_method_code' => $validated['paymentMethodCode'],
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('paymentAmount', $exception->getMessage());

            return;
        }

        // El modal se queda abierto (no se cierra el ledger) para que se vea
        // de una vez el movimiento de pago recien creado.
        $this->paymentAmount = '';
        $this->paymentReference = '';
        $this->paymentMethodCode = 'cash';
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

    public function paymentMethodLabel(?string $code): ?string
    {
        return match ($code) {
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia',
            default => null,
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

    // Evita crear un proveedor duplicado si el nombre tecleado coincide
    // exactamente (sin distinguir mayusculas) con uno que ya existe pero
    // el usuario no lo selecciono de la lista desplegable.
    protected function resolveOrCreateSupplierByName(Company $company, string $name): Supplier
    {
        $existing = Supplier::query()
            ->where('company_id', $company->id)
            ->whereHas('person', fn ($query) => $query->whereRaw('LOWER(first_name) = ?', [mb_strtolower($name)]))
            ->first();

        if ($existing) {
            return $existing;
        }

        return app(CreateSupplier::class)->handle($company, ['first_name' => $name]);
    }

    protected function resetPurchaseForm(): void
    {
        $branches = $this->branches();
        $this->branchId = $branches->first()?->id;
        $this->warehouseId = $this->warehouses()->first()?->id;
        $this->supplierId = null;
        $this->supplierName = '';
        $this->invoiceNumber = '';
        // Casi todo negocio siempre usa el mismo tipo de documento; arrancar
        // con el ultimo que este usuario guardo evita que lo tenga que
        // cambiar cada vez.
        $this->purchaseType = auth()->user()?->last_purchase_type ?? 'invoice';
        $this->purchasedAt = now()->format('Y-m-d');
        $this->dueAt = '';
        $this->notes = '';
        $this->totalAmount = '';
        $this->paidImmediately = null;
        $this->paymentCompletionMode = 'full';
        $this->initialPaidAmount = '';
        $this->paymentMethodMode = 'cash';
        $this->mixedCashAmount = '';
        $this->mixedTransferAmount = '';
        $this->resetValidation();
    }

    protected function resetPaymentForm(): void
    {
        $this->reset('paymentAmount', 'paymentReference');
        $this->paymentMethodCode = 'cash';
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
