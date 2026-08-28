<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CreditAccount;
use App\Models\LoyaltyAccount;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationalReportService
{
    public function summaryCards(
        Company $company,
        array $filters = [],
        bool $includeCosts = false,
        bool $includeCredit = true,
        bool $includeLoyalty = true,
        bool $includePromotions = true,
    ): array {
        $sales = $this->salesQuery($company, $filters)->get();
        $payments = $this->paymentsQuery($company, $filters)->get();
        $openCashSessions = $this->cashSessionsOpenCount($company, $filters);

        $cards = [
            'sales_count' => $sales->count(),
            'confirmed_sales_count' => $sales->where('status', 'confirmed')->count(),
            'cancelled_sales_count' => $sales->where('status', 'cancelled')->count(),
            'returned_sales_count' => $sales->filter(fn (Sale $sale) => in_array($sale->status, ['returned', 'partially_returned'], true))->count(),
            'sales_total' => $this->money($sales->sum(fn (Sale $sale) => (float) $sale->grand_total)),
            'payments_total' => $this->money($payments->sum(fn (Payment $payment) => (float) $payment->amount)),
            'open_cash_sessions_count' => $openCashSessions,
        ];

        if ($includeCredit) {
            $creditAccounts = $this->creditAccountsQuery($company)->get();
            $cards['credit_balance_due'] = $this->money($creditAccounts->sum(fn (CreditAccount $account) => (float) $account->balance_due));

            $creditAging = $this->creditAging($company, $filters);
            $cards['credit_overdue_balance'] = $this->money(
                $creditAging->sum(fn (array $bucket) => in_array($bucket['bucket_code'], ['1_30', '31_60', '61_plus'], true) ? (float) str_replace(',', '', $bucket['balance_total']) : 0)
            );
            $cards['credit_overdue_sales_count'] = $creditAging
                ->filter(fn (array $bucket) => in_array($bucket['bucket_code'], ['1_30', '31_60', '61_plus'], true))
                ->sum('sales_count');
        }

        if ($includeLoyalty) {
            $loyaltyAccounts = $this->loyaltyAccountsQuery($company)->get();
            $cards['loyalty_points_balance'] = number_format((float) $loyaltyAccounts->sum(fn (LoyaltyAccount $account) => (float) $account->points_balance), 0, ',', '.');
        }

        if ($includePromotions) {
            $cards['active_promotions_count'] = $this->promotionsQuery($company)->count();
        }

        if ($includeCosts) {
            $cards['gross_margin_total'] = $this->money($this->grossMarginAmount($company, $filters));
        }

        return $cards;
    }

    /**
     * Total de ventas sin formatear, para el contraste "Ingresos vs Gastos"
     * del reporte (que necesita restar contra el total de compras, no
     * comparar strings ya formateados).
     */
    public function salesTotalRaw(Company $company, array $filters = []): float
    {
        return (float) $this->salesQuery($company, $filters)->sum('grand_total');
    }

    public function salesTrend(Company $company, array $filters = []): Collection
    {
        return $this->salesQuery($company, $filters)
            ->selectRaw('date(coalesce(sold_at, created_at)) as sale_date, count(*) as sales_count, coalesce(sum(grand_total), 0) as sales_total')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->sale_date,
                'sales_count' => (int) $row->sales_count,
                'sales_total' => (float) $row->sales_total,
            ]);
    }

    public function branchBreakdown(Company $company, array $filters = []): Collection
    {
        $sales = $this->salesQuery($company, $filters)
            ->select('branch_id', DB::raw('count(*) as sales_count'), DB::raw('coalesce(sum(grand_total), 0) as sales_total'))
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');
        $payments = $this->paymentsQuery($company, $filters)
            ->selectRaw('sales.branch_id as branch_id, count(payments.id) as payments_count, coalesce(sum(payments.amount), 0) as payments_total')
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->groupBy('sales.branch_id')
            ->get()
            ->keyBy('branch_id');

        return Branch::query()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(function (Branch $branch) use ($sales, $payments) {
                $branchSales = $sales->get($branch->id);
                $branchPayments = $payments->get($branch->id);

                return [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'sales_count' => (int) ($branchSales->sales_count ?? 0),
                    'sales_total' => $this->money((float) ($branchSales->sales_total ?? 0)),
                    'payments_count' => (int) ($branchPayments->payments_count ?? 0),
                    'payments_total' => $this->money((float) ($branchPayments->payments_total ?? 0)),
                ];
            });
    }

    public function topProducts(Company $company, array $filters = [], bool $includeCosts = false): Collection
    {
        $items = $this->saleItemsQuery($company, $filters)
            ->select(
                'product_id',
                DB::raw('coalesce(sum(quantity), 0) as quantity_sum'),
                DB::raw('coalesce(sum(line_total), 0) as revenue_sum'),
                DB::raw('coalesce(sum(case when cost_snapshot is null then 0 else (cost_snapshot * base_quantity) end), 0) as cost_sum')
            )
            ->groupBy('product_id')
            ->orderByDesc(DB::raw('coalesce(sum(line_total), 0)'))
            ->limit(12)
            ->get();

        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id')->all())
            ->get()
            ->keyBy('id');

        return $items->map(function ($row) use ($products, $includeCosts) {
            $product = $products->get($row->product_id);
            $payload = [
                'product_id' => (int) $row->product_id,
                'product_name' => $product?->name ?? 'Producto '.$row->product_id,
                'quantity_sum' => number_format((float) $row->quantity_sum, 2, '.', ','),
                'revenue_sum' => $this->money((float) $row->revenue_sum),
            ];

            if ($includeCosts) {
                $cost = (float) $row->cost_sum;
                $revenue = (float) $row->revenue_sum;
                $payload['cost_sum'] = $this->money($cost);
                $payload['margin_sum'] = $this->money($revenue - $cost);
            }

            return $payload;
        });
    }

    public function recentPromotions(Company $company): Collection
    {
        return $this->promotionsQuery($company)
            ->orderBy('priority')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Promotion $promotion) => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'status' => $promotion->status,
                'promotion_type' => $promotion->promotion_type,
                'starts_at' => optional($promotion->starts_at)?->format('Y-m-d H:i'),
                'ends_at' => optional($promotion->ends_at)?->format('Y-m-d H:i'),
            ]);
    }

    public function paymentMethodBreakdown(Company $company, array $filters = []): Collection
    {
        return $this->paymentsQuery($company, $filters)
            ->selectRaw('payment_method_code, count(*) as payments_count, coalesce(sum(amount), 0) as payments_total')
            ->groupBy('payment_method_code')
            ->orderByDesc(DB::raw('coalesce(sum(amount), 0)'))
            ->get()
            ->map(fn ($row) => [
                'payment_method_code' => (string) $row->payment_method_code,
                'payment_method_label' => $this->paymentMethodLabel((string) $row->payment_method_code),
                'payments_count' => (int) $row->payments_count,
                'payments_total' => $this->money((float) $row->payments_total),
            ]);
    }

    public function creditAging(Company $company, array $filters = [], ?CarbonInterface $asOf = null): Collection
    {
        $asOf ??= now();

        $sales = $this->salesQuery($company, $filters)
            ->whereNotNull('credit_account_id')
            ->whereNotIn('status', ['cancelled'])
            ->with(['creditMovements:id,sale_id,movement_type,amount'])
            ->get();

        $buckets = collect([
            ['bucket_code' => 'current', 'bucket_label' => 'Al dia', 'sales_count' => 0, 'balance_total' => '0.00'],
            ['bucket_code' => '1_30', 'bucket_label' => 'Vencido 1-30 dias', 'sales_count' => 0, 'balance_total' => '0.00'],
            ['bucket_code' => '31_60', 'bucket_label' => 'Vencido 31-60 dias', 'sales_count' => 0, 'balance_total' => '0.00'],
            ['bucket_code' => '61_plus', 'bucket_label' => 'Vencido 61+ dias', 'sales_count' => 0, 'balance_total' => '0.00'],
            ['bucket_code' => 'no_due_date', 'bucket_label' => 'Sin vencimiento', 'sales_count' => 0, 'balance_total' => '0.00'],
        ])->keyBy('bucket_code');

        foreach ($sales as $sale) {
            $outstanding = $this->outstandingForCreditSale($sale);

            if (bccomp($outstanding, '0.00', 2) <= 0) {
                continue;
            }

            $bucket = $this->creditAgingBucket($sale, $asOf);
            $current = $buckets->get($bucket);

            $current['sales_count']++;
            $current['balance_total'] = bcadd($current['balance_total'], $outstanding, 2);

            $buckets->put($bucket, $current);
        }

        return $buckets->values()->map(fn (array $bucket) => [
            ...$bucket,
            'balance_total' => $this->money((float) $bucket['balance_total']),
        ]);
    }

    protected function salesQuery(Company $company, array $filters = []): Builder
    {
        return Sale::query()
            ->where('company_id', $company->id)
            ->when($this->branchId($filters), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($this->cashRegisterId($filters), fn (Builder $query, int $cashRegisterId) => $query->where('cash_register_id', $cashRegisterId))
            ->when($this->dateFrom($filters), fn (Builder $query, string $dateFrom) => $query->whereDate(DB::raw('coalesce(sold_at, created_at)'), '>=', $dateFrom))
            ->when($this->dateTo($filters), fn (Builder $query, string $dateTo) => $query->whereDate(DB::raw('coalesce(sold_at, created_at)'), '<=', $dateTo));
    }

    protected function saleItemsQuery(Company $company, array $filters = []): Builder
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.company_id', $company->id)
            ->when($this->branchId($filters), fn (Builder $query, int $branchId) => $query->where('sales.branch_id', $branchId))
            ->when($this->cashRegisterId($filters), fn (Builder $query, int $cashRegisterId) => $query->where('sales.cash_register_id', $cashRegisterId))
            ->when($this->dateFrom($filters), fn (Builder $query, string $dateFrom) => $query->whereDate(DB::raw('coalesce(sales.sold_at, sales.created_at)'), '>=', $dateFrom))
            ->when($this->dateTo($filters), fn (Builder $query, string $dateTo) => $query->whereDate(DB::raw('coalesce(sales.sold_at, sales.created_at)'), '<=', $dateTo));
    }

    protected function paymentsQuery(Company $company, array $filters = []): Builder
    {
        return Payment::query()
            ->where('payments.company_id', $company->id)
            ->where('payments.status', 'confirmed')
            ->when($this->branchId($filters), function (Builder $query, int $branchId) {
                $query->whereHas('sale', fn (Builder $saleQuery) => $saleQuery->where('branch_id', $branchId));
            })
            ->when($this->cashRegisterId($filters), function (Builder $query, int $cashRegisterId) {
                $query->whereHas('sale', fn (Builder $saleQuery) => $saleQuery->where('cash_register_id', $cashRegisterId));
            })
            ->when($this->dateFrom($filters), fn (Builder $query, string $dateFrom) => $query->whereDate(DB::raw('coalesce(payments.paid_at, payments.created_at)'), '>=', $dateFrom))
            ->when($this->dateTo($filters), fn (Builder $query, string $dateTo) => $query->whereDate(DB::raw('coalesce(payments.paid_at, payments.created_at)'), '<=', $dateTo));
    }

    protected function creditAccountsQuery(Company $company): Builder
    {
        return CreditAccount::query()->where('company_id', $company->id);
    }

    protected function loyaltyAccountsQuery(Company $company): Builder
    {
        return LoyaltyAccount::query()->where('company_id', $company->id);
    }

    protected function promotionsQuery(Company $company): Builder
    {
        return Promotion::query()->where('company_id', $company->id);
    }

    protected function cashSessionsOpenCount(Company $company, array $filters = []): int
    {
        return $company->cashSessions()
            ->where('status', 'open')
            ->when($this->branchId($filters), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->count();
    }

    protected function grossMarginAmount(Company $company, array $filters = []): float
    {
        return (float) $this->saleItemsQuery($company, $filters)
            ->selectRaw('coalesce(sum(line_total - (case when cost_snapshot is null then 0 else (cost_snapshot * base_quantity) end)), 0) as margin_sum')
            ->value('margin_sum');
    }

    protected function outstandingForCreditSale(Sale $sale): string
    {
        return $sale->creditMovements->reduce(function (string $carry, $movement) {
            $direction = $movement->movement_type === 'sale_charge' ? 1 : -1;

            return $direction === 1
                ? bcadd($carry, (string) $movement->amount, 2)
                : bcsub($carry, (string) $movement->amount, 2);
        }, '0.00');
    }

    protected function creditAgingBucket(Sale $sale, CarbonInterface $asOf): string
    {
        if (! $sale->credit_due_at) {
            return 'no_due_date';
        }

        if ($sale->credit_due_at->greaterThanOrEqualTo($asOf->copy()->startOfDay())) {
            return 'current';
        }

        $daysOverdue = (int) $sale->credit_due_at->diffInDays($asOf);

        return match (true) {
            $daysOverdue <= 30 => '1_30',
            $daysOverdue <= 60 => '31_60',
            default => '61_plus',
        };
    }

    protected function paymentMethodLabel(string $code): string
    {
        return match ($code) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'credit' => 'Credito',
            default => ucfirst(str_replace(['_', '-'], ' ', $code)),
        };
    }

    protected function branchId(array $filters): ?int
    {
        $branchId = $filters['branch_id'] ?? null;

        return $branchId !== null && $branchId !== '' ? (int) $branchId : null;
    }

    protected function cashRegisterId(array $filters): ?int
    {
        $cashRegisterId = $filters['cash_register_id'] ?? null;

        return $cashRegisterId !== null && $cashRegisterId !== '' ? (int) $cashRegisterId : null;
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
