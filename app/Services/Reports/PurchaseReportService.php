<?php

namespace App\Services\Reports;

use App\Enums\PurchaseStatus;
use App\Models\Company;
use App\Models\PayableMovement;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Espejo de OperationalReportService pero para el lado de compras (gasto),
 * para que el reporte pueda mostrar Compras y Ventas una al lado de la otra
 * con la misma forma de tarjetas/tendencia/medio de pago.
 */
class PurchaseReportService
{
    public function summaryCards(Company $company, array $filters = []): array
    {
        $purchases = $this->purchasesQuery($company, $filters)->get();
        $active = $purchases->filter(fn (Purchase $purchase) => $purchase->status !== PurchaseStatus::Cancelled->value);
        $payments = $this->paymentMovementsQuery($company, $filters)->get();

        return [
            'purchases_count' => $purchases->count(),
            'confirmed_purchases_count' => $purchases->whereIn('status', [
                PurchaseStatus::Confirmed->value,
                PurchaseStatus::PartiallyPaid->value,
                PurchaseStatus::Paid->value,
            ])->count(),
            'cancelled_purchases_count' => $purchases->where('status', PurchaseStatus::Cancelled->value)->count(),
            'purchases_total' => $this->money($active->sum(fn (Purchase $purchase) => (float) $purchase->total)),
            'payments_total' => $this->money($payments->sum(fn (PayableMovement $movement) => (float) $movement->amount)),
            'payables_balance_due' => $this->money($this->outstandingPayablesTotal($company)),
        ];
    }

    /**
     * Espejo de OperationalReportService::salesTotalRaw() — total sin
     * formatear para el contraste "Ingresos vs Gastos" del reporte.
     */
    public function purchasesTotalRaw(Company $company, array $filters = []): float
    {
        return (float) $this->purchasesQuery($company, $filters)
            ->where('status', '!=', PurchaseStatus::Cancelled->value)
            ->sum('total');
    }

    public function purchasesTrend(Company $company, array $filters = []): Collection
    {
        return $this->purchasesQuery($company, $filters)
            ->where('status', '!=', PurchaseStatus::Cancelled->value)
            ->selectRaw('date(coalesce(purchased_at, created_at)) as purchase_date, count(*) as purchases_count, coalesce(sum(total), 0) as purchases_total')
            ->groupBy('purchase_date')
            ->orderBy('purchase_date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->purchase_date,
                'purchases_count' => (int) $row->purchases_count,
                'purchases_total' => (float) $row->purchases_total,
            ]);
    }

    public function paymentMethodBreakdown(Company $company, array $filters = []): Collection
    {
        return $this->paymentMovementsQuery($company, $filters)
            ->selectRaw('payment_method_code, count(*) as payments_count, coalesce(sum(amount), 0) as payments_total')
            ->groupBy('payment_method_code')
            ->orderByDesc(DB::raw('coalesce(sum(amount), 0)'))
            ->get()
            ->map(fn ($row) => [
                'payment_method_code' => $row->payment_method_code,
                'payment_method_label' => $this->paymentMethodLabel($row->payment_method_code),
                'payments_count' => (int) $row->payments_count,
                'payments_total' => $this->money((float) $row->payments_total),
            ]);
    }

    public function topSuppliers(Company $company, array $filters = []): Collection
    {
        $rows = $this->purchasesQuery($company, $filters)
            ->where('status', '!=', PurchaseStatus::Cancelled->value)
            ->select(
                'supplier_id',
                'supplier_name',
                DB::raw('count(*) as purchases_count'),
                DB::raw('coalesce(sum(total), 0) as total_sum')
            )
            ->groupBy('supplier_id', 'supplier_name')
            ->orderByDesc(DB::raw('coalesce(sum(total), 0)'))
            ->limit(6)
            ->get();

        $suppliers = Supplier::query()
            ->whereIn('id', $rows->pluck('supplier_id')->filter()->all())
            ->with('person')
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($suppliers) {
            $supplier = $row->supplier_id ? $suppliers->get($row->supplier_id) : null;
            $name = $supplier
                ? trim(implode(' ', array_filter([$supplier->person?->first_name, $supplier->person?->last_name])))
                : null;

            return [
                'supplier_label' => $name ?: ($row->supplier_name ?: 'Sin proveedor formal'),
                'purchases_count' => (int) $row->purchases_count,
                'total_sum' => $this->money((float) $row->total_sum),
            ];
        });
    }

    protected function purchasesQuery(Company $company, array $filters = []): Builder
    {
        return Purchase::query()
            ->where('company_id', $company->id)
            ->when($this->branchId($filters), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($this->dateFrom($filters), fn (Builder $query, string $dateFrom) => $query->whereDate(DB::raw('coalesce(purchased_at, created_at)'), '>=', $dateFrom))
            ->when($this->dateTo($filters), fn (Builder $query, string $dateTo) => $query->whereDate(DB::raw('coalesce(purchased_at, created_at)'), '<=', $dateTo));
    }

    protected function paymentMovementsQuery(Company $company, array $filters = []): Builder
    {
        return PayableMovement::query()
            ->where('payable_movements.company_id', $company->id)
            ->where('payable_movements.movement_type', 'payment')
            ->when($this->branchId($filters), function (Builder $query, int $branchId) {
                $query->whereHas('purchase', fn (Builder $purchaseQuery) => $purchaseQuery->where('branch_id', $branchId));
            })
            ->when($this->dateFrom($filters), fn (Builder $query, string $dateFrom) => $query->whereDate(DB::raw('coalesce(payable_movements.occurred_at, payable_movements.created_at)'), '>=', $dateFrom))
            ->when($this->dateTo($filters), fn (Builder $query, string $dateTo) => $query->whereDate(DB::raw('coalesce(payable_movements.occurred_at, payable_movements.created_at)'), '<=', $dateTo));
    }

    /**
     * Saldo pendiente con proveedores AHORA, sin importar el rango de fechas
     * del filtro — mismo criterio que "Cartera" en el lado de ventas
     * (CreditAccount::balance_due tampoco se filtra por fecha): es una foto
     * del momento, no del periodo consultado.
     */
    protected function outstandingPayablesTotal(Company $company): float
    {
        return (float) Purchase::query()
            ->where('company_id', $company->id)
            ->where('balance_due', '>', 0)
            ->sum('balance_due');
    }

    protected function paymentMethodLabel(?string $code): string
    {
        return match ($code) {
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia',
            default => 'Sin especificar',
        };
    }

    protected function branchId(array $filters): ?int
    {
        $branchId = $filters['branch_id'] ?? null;

        return $branchId !== null && $branchId !== '' ? (int) $branchId : null;
    }

    protected function dateFrom(array $filters): ?string
    {
        return $this->blankToNull($filters['date_from'] ?? null);
    }

    protected function dateTo(array $filters): ?string
    {
        return $this->blankToNull($filters['date_to'] ?? null);
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function money(float $value): string
    {
        return Money::format($value);
    }
}
