<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\ApplySupplierCreditToPurchase;
use App\Actions\Purchases\ListPurchasePayables;
use App\Actions\Purchases\ListSupplierPayablesSummary;
use App\Enums\PayableMovementType;
use App\Enums\PurchaseStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\PayableMovement;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class PayablesPage extends Component
{
    use InteractsWithToast;

    public ?int $supplierId = null;
    public string $supplierName = '';
    public string $status = 'open';
    public bool $overdueOnly = false;
    public bool $hasCreditOnly = false;
    public string $agingBucket = '';
    public string $dueFrom = '';
    public string $dueTo = '';
    public ?int $applyingPurchaseId = null;
    public string $creditAmount = '';
    public string $creditReference = '';
    public ?int $expandedLedgerPurchaseId = null;

    public function mount(): void
    {
        $this->ensurePermission('payables.view');
    }

    public function setStatus(string $status): void
    {
        if (! in_array($status, ['open', '', 'confirmed', 'partially_paid', 'paid', 'cancelled'], true)) {
            return;
        }

        $this->status = $status;
    }

    public function startApplyingCredit(int $purchaseId): void
    {
        $this->ensurePermission('payables.manage');

        $purchase = $this->purchasesQuery()->with('supplier')->findOrFail($purchaseId);
        $supplierCredit = (string) ($purchase->supplier?->credit_balance ?? '0.00');

        if ($purchase->supplier_id === null || bccomp($supplierCredit, '0.00', 2) <= 0) {
            $this->toast('El proveedor no tiene saldo a favor disponible para aplicar.', 'warning');

            return;
        }

        $this->applyingPurchaseId = $purchase->id;
        $suggestedAmount = bccomp((string) $purchase->balance_due, $supplierCredit, 2) === 1
            ? $supplierCredit
            : (string) $purchase->balance_due;
        $this->creditAmount = (string) (int) round((float) $suggestedAmount);
        $this->creditReference = '';
        $this->resetValidation();
    }

    public function cancelApplyingCredit(): void
    {
        $this->resetCreditForm();
    }

    public function toggleLedger(int $purchaseId): void
    {
        $this->expandedLedgerPurchaseId = $this->expandedLedgerPurchaseId === $purchaseId
            ? null
            : $purchaseId;
    }

    public function applySupplierCredit(ApplySupplierCreditToPurchase $action): void
    {
        $this->ensurePermission('payables.manage');

        $validated = $this->validate([
            'applyingPurchaseId' => [
                'required',
                Rule::exists('purchases', 'id')->where(fn ($query) => $query->where('company_id', $this->currentCompany()->id)),
            ],
            'creditAmount' => ['required', 'numeric', 'gt:0'],
            'creditReference' => ['required', 'string', 'max:120'],
        ]);

        $purchase = $this->purchasesQuery()->findOrFail((int) $validated['applyingPurchaseId']);

        try {
            $action->handle($this->currentCompany(), $purchase, [
                'amount' => $validated['creditAmount'],
                'reference' => trim($validated['creditReference']),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('creditAmount', $exception->getMessage());

            return;
        }

        $this->resetCreditForm();
        $this->toast('Saldo a favor aplicado correctamente.');
    }

    public function payables(ListPurchasePayables $listPurchasePayables): Collection
    {
        return $listPurchasePayables->handle($this->currentCompany(), [
            'supplier_id' => $this->supplierId,
            'supplier_name' => $this->supplierName,
            'status' => $this->status,
            'overdue_only' => $this->overdueOnly,
            'has_credit_only' => $this->hasCreditOnly,
            'aging_bucket' => $this->agingBucket,
            'due_from' => $this->dueFrom,
            'due_to' => $this->dueTo,
        ]);
    }

    public function suppliers(): Collection
    {
        return Supplier::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with('person')
            ->orderByDesc('credit_balance')
            ->orderBy('id')
            ->get();
    }

    public function supplierSummary(ListSupplierPayablesSummary $listSupplierPayablesSummary): SupportCollection
    {
        return $listSupplierPayablesSummary->handle($this->currentCompany(), [
            'supplier_id' => $this->supplierId,
            'supplier_name' => $this->supplierName,
            'has_balance_only' => $this->status === 'open' && ! $this->hasCreditOnly,
            'overdue_only' => $this->overdueOnly,
            'has_credit_only' => $this->hasCreditOnly,
            'aging_bucket' => $this->agingBucket,
        ]);
    }

    public function render(): View
    {
        $payables = $this->payables(app(ListPurchasePayables::class));
        $supplierSummary = $this->supplierSummary(app(ListSupplierPayablesSummary::class));

        return view('livewire.purchases.payables-page', [
            'payables' => $payables,
            'suppliers' => $this->suppliers(),
            'supplierSummary' => $supplierSummary,
            'statusCards' => $this->statusCards(
                $payables,
                (float) $supplierSummary->sum(fn (array $row) => (float) $row['credit_balance'])
            ),
            'agingChartData' => $this->agingChartData($supplierSummary),
            'topSuppliersChartData' => $this->topSuppliersChartData($supplierSummary),
            'paymentMethodBreakdown' => $this->paymentMethodBreakdown($payables),
            // Si los filtros activos no dejan ningun proveedor/compra visible, las 3
            // tarjetas quedan en $0 a la vez y parecen rotas; esto distingue ese caso
            // (filtro estrecho) de una empresa que genuinamente no tiene deuda.
            'emptyDueToFilters' => $payables->isEmpty() && $supplierSummary->isEmpty() && $this->hasNonDefaultFilters(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Cuentas por Pagar',
                'description' => 'Consulta compras pendientes, vencimientos y aplica saldo a favor del proveedor sobre compras abiertas.',
            ]),
        ]);
    }

    /**
     * De donde salio el dinero con el que se les pago a proveedores
     * (efectivo/transferencia), calculado sobre los mismos movimientos de
     * pago que ya trae cargados $payables — respeta los mismos filtros que
     * la tabla de abajo (proveedor, estado, vencidas, etc.) sin otra
     * consulta aparte.
     *
     * @return array<int, array{payment_method_code: ?string, payment_method_label: string, payments_count: int, payments_total: string}>
     */
    protected function paymentMethodBreakdown(Collection $payables): array
    {
        return $payables
            ->flatMap(fn (Purchase $purchase) => $purchase->payableMovements)
            ->where('movement_type', PayableMovementType::Payment->value)
            ->groupBy(fn (PayableMovement $movement) => $movement->payment_method_code ?? '')
            ->map(fn (SupportCollection $group, string $code) => [
                'payment_method_code' => $code !== '' ? $code : null,
                'payment_method_label' => $this->paymentMethodLabel($code !== '' ? $code : null) ?? 'Sin especificar',
                'payments_count' => $group->count(),
                'amount_raw' => (float) $group->sum('amount'),
            ])
            ->sortByDesc('amount_raw')
            ->map(fn (array $row) => [
                'payment_method_code' => $row['payment_method_code'],
                'payment_method_label' => $row['payment_method_label'],
                'payments_count' => $row['payments_count'],
                'payments_total' => \App\Support\Money::format($row['amount_raw']),
            ])
            ->values()
            ->all();
    }

    /**
     * Indicadores de la cabecera: a diferencia de $supplierSummary (que
     * siempre resume la deuda abierta global, sin importar que pestaña de
     * estado este activa), esto se calcula sobre $payables — la lista que
     * YA respeta la pestaña Pendientes/Todas/Pagadas y el resto de filtros —
     * para que las tarjetas de arriba nunca contradigan lo que se ve en la
     * tabla de abajo. Antes las 3 tarjetas eran fijas ("Debes en total" /
     * "Vencido" / "A tu favor") aunque estuvieras viendo "Pagadas", donde
     * esos numeros no significan nada.
     */
    protected function statusCards(Collection $payables, float $availableCreditTotal): array
    {
        $today = now()->startOfDay();

        // Una compra cancelada no es deuda ni gasto real; se excluye de
        // los totales en dinero aunque siga apareciendo en la tabla "Todas".
        $active = $payables->filter(fn (Purchase $purchase) => $purchase->status !== PurchaseStatus::Cancelled->value);

        $overdue = $active->filter(fn (Purchase $purchase) => (float) $purchase->balance_due > 0
            && $purchase->due_at !== null
            && $purchase->due_at->lt($today));

        $agingBucketSum = fn ($from, $to) => $overdue
            ->filter(fn (Purchase $purchase) => (! $from || $purchase->due_at->gte($from)) && (! $to || $purchase->due_at->lt($to)))
            ->sum(fn (Purchase $purchase) => (float) $purchase->balance_due);

        $paidPurchases = $active->filter(fn (Purchase $purchase) => $purchase->status === PurchaseStatus::Paid->value);

        // Sin fecha limite no hay como llegar tarde: cuenta como a tiempo,
        // igual que el semaforo de mora de Credito trata "sin vencimiento".
        $paidOnTime = $paidPurchases->filter(fn (Purchase $purchase) => $purchase->due_at === null
            || $purchase->paid_at === null
            || $purchase->paid_at->startOfDay()->lte($purchase->due_at->startOfDay()));

        return [
            'mode' => match ($this->status) {
                'open' => 'open',
                'paid' => 'paid',
                default => 'all',
            },
            'purchases_count' => $active->count(),
            'total_amount' => $active->sum(fn (Purchase $purchase) => (float) $purchase->total),
            'paid_amount' => $active->sum(fn (Purchase $purchase) => (float) $purchase->amount_paid),
            'pending_amount' => $active->sum(fn (Purchase $purchase) => (float) $purchase->balance_due),
            'overdue_amount' => $overdue->sum(fn (Purchase $purchase) => (float) $purchase->balance_due),
            'overdue_count' => $overdue->count(),
            'available_credit_total' => $availableCreditTotal,
            'aging' => [
                '0_30' => $agingBucketSum($today->copy()->subDays(30), null),
                '31_60' => $agingBucketSum($today->copy()->subDays(60), $today->copy()->subDays(30)),
                '61_90' => $agingBucketSum($today->copy()->subDays(90), $today->copy()->subDays(60)),
                '91_plus' => $agingBucketSum(null, $today->copy()->subDays(90)),
            ],
            'paid_purchases_count' => $paidPurchases->count(),
            'paid_on_time_count' => $paidOnTime->count(),
            'paid_late_count' => $paidPurchases->count() - $paidOnTime->count(),
        ];
    }

    /**
     * Cuanto se debe en total por cada tramo de antiguedad (0-30, 31-60,
     * 61-90, 91+ dias), sumado entre todos los proveedores filtrados —
     * mismo criterio de color que el semaforo de compras (verde = reciente,
     * rojo = mas viejo).
     */
    protected function agingChartData(SupportCollection $supplierSummary): array
    {
        return [
            ['label' => '0-30 dias', 'value' => $supplierSummary->sum(fn (array $row) => (float) $row['age_0_30_balance_total'])],
            ['label' => '31-60 dias', 'value' => $supplierSummary->sum(fn (array $row) => (float) $row['age_31_60_balance_total'])],
            ['label' => '61-90 dias', 'value' => $supplierSummary->sum(fn (array $row) => (float) $row['age_61_90_balance_total'])],
            ['label' => '91+ dias', 'value' => $supplierSummary->sum(fn (array $row) => (float) $row['age_91_plus_balance_total'])],
        ];
    }

    /**
     * Los proveedores con mayor saldo pendiente entre los filtrados, para
     * que de un vistazo se vea a quien se le debe mas sin tener que leer
     * toda la tabla de compras.
     */
    protected function topSuppliersChartData(SupportCollection $supplierSummary): array
    {
        return $supplierSummary
            ->filter(fn (array $row) => (float) $row['open_balance_total'] > 0)
            ->sortByDesc(fn (array $row) => (float) $row['open_balance_total'])
            ->take(6)
            ->map(fn (array $row) => ['label' => $row['supplier_name'], 'value' => (float) $row['open_balance_total']])
            ->values()
            ->all();
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

    /**
     * True si hay algun filtro distinto de su valor por defecto (estado
     * "open" incluido). Sirve para distinguir "sin deuda porque el filtro
     * actual es muy estrecho" de "sin deuda porque la empresa esta al dia".
     */
    protected function hasNonDefaultFilters(): bool
    {
        return $this->status !== 'open'
            || $this->supplierId !== null
            || $this->supplierName !== ''
            || $this->overdueOnly
            || $this->hasCreditOnly
            || $this->agingBucket !== ''
            || $this->dueFrom !== ''
            || $this->dueTo !== '';
    }

    protected function resetCreditForm(): void
    {
        $this->reset('applyingPurchaseId', 'creditAmount', 'creditReference');
        $this->resetValidation();
    }
}
