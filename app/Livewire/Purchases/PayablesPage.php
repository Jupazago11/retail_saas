<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\ApplySupplierCreditToPurchase;
use App\Actions\Purchases\ListPurchasePayables;
use App\Actions\Purchases\ListSupplierPayablesSummary;
use App\Enums\PayableMovementType;
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
            'openBalanceTotal' => $payables->sum(fn (Purchase $purchase) => (float) $purchase->balance_due),
            'overdueBalanceTotal' => $supplierSummary->sum(fn (array $row) => (float) $row['overdue_balance_total']),
            'availableCreditTotal' => $supplierSummary->sum(fn (array $row) => (float) $row['credit_balance']),
            'netExposureTotal' => $supplierSummary->sum(fn (array $row) => (float) $row['net_balance_exposure']),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Cuentas por Pagar',
                'description' => 'Consulta compras pendientes, vencimientos y aplica saldo a favor del proveedor sobre compras abiertas.',
            ]),
        ]);
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

    protected function resetCreditForm(): void
    {
        $this->reset('applyingPurchaseId', 'creditAmount', 'creditReference');
        $this->resetValidation();
    }
}
