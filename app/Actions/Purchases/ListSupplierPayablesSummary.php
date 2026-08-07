<?php

namespace App\Actions\Purchases;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class ListSupplierPayablesSummary
{
    public function handle(Company $company, array $filters = []): Collection
    {
        $today = now()->startOfDay();

        $query = Supplier::query()
            ->where('company_id', $company->id)
            ->with('person')
            ->withCount([
                'purchases as open_purchases_count' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0),
                'purchases as overdue_purchases_count' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $today),
            ])
            ->withSum([
                'purchases as open_balance_total' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0),
                'purchases as current_balance_total' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0)
                    ->where(function ($nested) {
                        $nested
                            ->whereNull('due_at')
                            ->orWhere('due_at', '>=', now()->startOfDay());
                    }),
                'purchases as overdue_balance_total' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $today),
                'purchases as age_0_30_balance_total' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $today)
                    ->where('due_at', '>=', $today->copy()->subDays(30)),
                'purchases as age_31_60_balance_total' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $today->copy()->subDays(30))
                    ->where('due_at', '>=', $today->copy()->subDays(60)),
                'purchases as age_61_90_balance_total' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $today->copy()->subDays(60))
                    ->where('due_at', '>=', $today->copy()->subDays(90)),
                'purchases as age_91_plus_balance_total' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $today->copy()->subDays(90)),
            ], 'balance_due')
            ->withMax('payableMovements as last_movement_at', 'occurred_at')
            ->withMin([
                'purchases as next_due_at' => fn ($purchaseQuery) => $purchaseQuery
                    ->where('balance_due', '>', 0)
                    ->whereNotNull('due_at'),
            ], 'due_at');

        $supplierId = $filters['supplier_id'] ?? null;

        if ($supplierId !== null && $supplierId !== '') {
            $query->where('id', (int) $supplierId);
        }

        $supplierName = $this->blankToNull($filters['supplier_name'] ?? null);

        if ($supplierName !== null) {
            $query->whereHas('person', function ($personQuery) use ($supplierName) {
                $personQuery->where(function ($nested) use ($supplierName) {
                    $nested
                        ->whereLike('first_name', '%' . $supplierName . '%')
                        ->orWhereLike('last_name', '%' . $supplierName . '%')
                        ->orWhereLike('document_number', '%' . $supplierName . '%');
                });
            });
        }

        if (($filters['has_balance_only'] ?? false) === true) {
            $query->whereHas('purchases', fn ($purchaseQuery) => $purchaseQuery->where('balance_due', '>', 0));
        }

        if (($filters['overdue_only'] ?? false) === true) {
            $query->whereHas('purchases', fn ($purchaseQuery) => $purchaseQuery
                ->where('balance_due', '>', 0)
                ->whereNotNull('due_at')
                ->where('due_at', '<', $today));
        }

        if (($filters['has_credit_only'] ?? false) === true) {
            $query->where('credit_balance', '>', 0);
        }

        $agingBucket = $this->blankToNull($filters['aging_bucket'] ?? null);

        if ($agingBucket !== null) {
            $query->whereHas('purchases', function ($purchaseQuery) use ($agingBucket, $today) {
                $purchaseQuery
                    ->where('balance_due', '>', 0)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $today);

                match ($agingBucket) {
                    '0_30' => $purchaseQuery->where('due_at', '>=', $today->copy()->subDays(30)),
                    '31_60' => $purchaseQuery
                        ->where('due_at', '<', $today->copy()->subDays(30))
                        ->where('due_at', '>=', $today->copy()->subDays(60)),
                    '61_90' => $purchaseQuery
                        ->where('due_at', '<', $today->copy()->subDays(60))
                        ->where('due_at', '>=', $today->copy()->subDays(90)),
                    '91_plus' => $purchaseQuery->where('due_at', '<', $today->copy()->subDays(90)),
                    default => throw new \InvalidArgumentException('Bucket de aging invalido.'),
                };
            });
        }

        return $query
            ->orderByDesc('open_balance_total')
            ->orderByDesc('credit_balance')
            ->orderBy('id')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'supplier_id' => $supplier->id,
                'supplier_name' => trim(implode(' ', array_filter([
                    $supplier->person?->first_name,
                    $supplier->person?->last_name,
                ]))) ?: 'Proveedor ' . $supplier->id,
                'document_number' => $supplier->person?->document_number,
                'status' => $supplier->status,
                'credit_balance' => bcadd((string) $supplier->credit_balance, '0', 2),
                'open_balance_total' => bcadd((string) ($supplier->open_balance_total ?? '0'), '0', 2),
                'current_balance_total' => bcadd((string) ($supplier->current_balance_total ?? '0'), '0', 2),
                'overdue_balance_total' => bcadd((string) ($supplier->overdue_balance_total ?? '0'), '0', 2),
                'age_0_30_balance_total' => bcadd((string) ($supplier->age_0_30_balance_total ?? '0'), '0', 2),
                'age_31_60_balance_total' => bcadd((string) ($supplier->age_31_60_balance_total ?? '0'), '0', 2),
                'age_61_90_balance_total' => bcadd((string) ($supplier->age_61_90_balance_total ?? '0'), '0', 2),
                'age_91_plus_balance_total' => bcadd((string) ($supplier->age_91_plus_balance_total ?? '0'), '0', 2),
                'net_balance_exposure' => bcsub(
                    bcadd((string) ($supplier->open_balance_total ?? '0'), '0', 2),
                    bcadd((string) $supplier->credit_balance, '0', 2),
                    2
                ),
                'open_purchases_count' => (int) ($supplier->open_purchases_count ?? 0),
                'overdue_purchases_count' => (int) ($supplier->overdue_purchases_count ?? 0),
                'last_movement_at' => $supplier->last_movement_at,
                'next_due_at' => $supplier->next_due_at,
            ])
            ->values();
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
