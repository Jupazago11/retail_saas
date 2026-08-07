<?php

namespace App\Actions\Purchases;

use App\Models\Company;
use App\Models\Purchase;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ListPurchasePayables
{
    public function handle(Company $company, array $filters = []): Collection
    {
        $today = now()->startOfDay();

        $query = Purchase::query()
            ->where('company_id', $company->id)
            ->where('status', '!=', 'draft')
            ->with(['payableMovements', 'supplier.person']);

        if (isset($filters['supplier_id']) && $filters['supplier_id'] !== null && $filters['supplier_id'] !== '') {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        $supplierName = $this->blankToNull($filters['supplier_name'] ?? null);

        if ($supplierName !== null) {
            $query->whereLike('supplier_name', '%' . $supplierName . '%');
        }

        $status = $this->blankToNull($filters['status'] ?? null);

        if ($status !== null) {
            if ($status === 'open') {
                $query->where('balance_due', '>', 0);
            } else {
                $query->where('status', $status);
            }
        }

        if (($filters['overdue_only'] ?? false) === true) {
            $query
                ->where('balance_due', '>', 0)
                ->whereNotNull('due_at')
                ->where('due_at', '<', $today);
        }

        if (($filters['has_credit_only'] ?? false) === true) {
            $query->whereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('credit_balance', '>', 0));
        }

        $agingBucket = $this->blankToNull($filters['aging_bucket'] ?? null);

        if ($agingBucket !== null) {
            $this->applyAgingBucket($query, $agingBucket, $today);
        }

        $dueFrom = $this->blankToNull($filters['due_from'] ?? null);

        if ($dueFrom !== null) {
            $query->whereDate('due_at', '>=', $this->normalizeDate($dueFrom));
        }

        $dueTo = $this->blankToNull($filters['due_to'] ?? null);

        if ($dueTo !== null) {
            $query->whereDate('due_at', '<=', $this->normalizeDate($dueTo));
        }

        return $query
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->get();
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeDate(string $value): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Fecha invalida.');
        }

        return $value;
    }

    protected function applyAgingBucket($query, string $agingBucket, $today): void
    {
        $query
            ->where('balance_due', '>', 0)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $today);

        match ($agingBucket) {
            '0_30' => $query->where('due_at', '>=', $today->copy()->subDays(30)),
            '31_60' => $query
                ->where('due_at', '<', $today->copy()->subDays(30))
                ->where('due_at', '>=', $today->copy()->subDays(60)),
            '61_90' => $query
                ->where('due_at', '<', $today->copy()->subDays(60))
                ->where('due_at', '>=', $today->copy()->subDays(90)),
            '91_plus' => $query->where('due_at', '<', $today->copy()->subDays(90)),
            default => throw new InvalidArgumentException('Bucket de aging invalido.'),
        };
    }
}
