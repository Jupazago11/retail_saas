<?php

namespace App\Actions\Purchases;

use App\Models\Company;
use App\Models\PayableMovement;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ListSupplierPayableMovements
{
    public function handle(Company $company, array $filters = []): Collection
    {
        $query = PayableMovement::query()
            ->where('company_id', $company->id)
            ->whereNotNull('supplier_id')
            ->with(['supplier.person', 'purchase']);

        $supplierId = $filters['supplier_id'] ?? null;

        if ($supplierId !== null && $supplierId !== '') {
            $query->where('supplier_id', (int) $supplierId);
        }

        $purchaseId = $filters['purchase_id'] ?? null;

        if ($purchaseId !== null && $purchaseId !== '') {
            $query->where('purchase_id', (int) $purchaseId);
        }

        $movementType = $this->blankToNull($filters['movement_type'] ?? null);

        if ($movementType !== null) {
            $query->where('movement_type', $movementType);
        }

        $supplierName = $this->blankToNull($filters['supplier_name'] ?? null);

        if ($supplierName !== null) {
            $query->whereHas('supplier.person', function ($personQuery) use ($supplierName) {
                $personQuery->where(function ($nested) use ($supplierName) {
                    $nested
                        ->whereLike('first_name', '%' . $supplierName . '%')
                        ->orWhereLike('last_name', '%' . $supplierName . '%')
                        ->orWhereLike('document_number', '%' . $supplierName . '%');
                });
            });
        }

        $dateFrom = $this->blankToNull($filters['date_from'] ?? null);

        if ($dateFrom !== null) {
            $query->whereDate('occurred_at', '>=', $this->normalizeDate($dateFrom));
        }

        $dateTo = $this->blankToNull($filters['date_to'] ?? null);

        if ($dateTo !== null) {
            $query->whereDate('occurred_at', '<=', $this->normalizeDate($dateTo));
        }

        return $query
            ->orderByDesc('occurred_at')
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
}
