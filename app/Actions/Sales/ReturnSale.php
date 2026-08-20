<?php

namespace App\Actions\Sales;

use App\Models\Company;
use App\Models\Sale;
use App\Services\Audit\AuditLogger;
use App\Services\Credit\CreditLedger;
use App\Services\Inventory\ReturnSaleToInventory;
use App\Services\Loyalty\LoyaltyLedger;
use InvalidArgumentException;

class ReturnSale
{
    public function __construct(
        protected ReturnSaleToInventory $returnSaleToInventory,
        protected CreditLedger $creditLedger,
        protected LoyaltyLedger $loyaltyLedger,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, Sale $sale, array $items, ?string $reason = null): Sale
    {
        if ($sale->company_id !== $company->id) {
            throw new InvalidArgumentException('La venta no pertenece a la empresa indicada.');
        }

        $reason = $this->normalizeReason($reason);

        $sale = $sale->fresh(['items', 'payments', 'creditAccount', 'customer.loyaltyAccount']);
        $beforeSale = $sale->fresh();

        $returnedAmount = $this->calculateReturnedAmount($sale, $items);

        $sale = $this->returnSaleToInventory->handle($sale, $items);

        if ($returnedAmount !== '0.00' && $sale->creditAccount) {
            $this->creditLedger->recordSaleReturnAdjustment($sale->creditAccount, $sale, $returnedAmount);
        }

        if ($returnedAmount !== '0.00' && $sale->customer?->loyaltyAccount) {
            $this->loyaltyLedger->restoreRedeemedPointsForReturnedAmount($sale->customer->loyaltyAccount, $sale, $returnedAmount);
            $this->loyaltyLedger->reverseForReturnedAmount($sale->customer->loyaltyAccount, $sale, $returnedAmount);
        }

        $sale->update([
            'notes' => $this->appendOperationalReason($sale->notes, 'Devolucion', $reason),
        ]);

        $sale = $sale->fresh(['items', 'payments', 'creditAccount', 'customer.loyaltyAccount']);
        $this->auditLogger->logUpdated($company, 'sale.returned', $beforeSale, $sale);

        return $sale;
    }

    protected function calculateReturnedAmount(Sale $sale, array $items): string
    {
        $saleItems = $sale->items->keyBy('id');

        return collect($items)->reduce(function (string $carry, array $item) use ($saleItems) {
            $saleItem = $saleItems->get((int) ($item['sale_item_id'] ?? 0));

            if (! $saleItem) {
                throw new InvalidArgumentException('La linea indicada no pertenece a la venta.');
            }

            $quantity = $this->normalizeQuantity($item['quantity'] ?? null);
            $unitLineTotal = bcdiv((string) $saleItem->line_total, (string) $saleItem->quantity, 6);
            $lineReturnedAmount = $this->roundMoney(bcmul($unitLineTotal, $quantity, 6));

            return bcadd($carry, $lineReturnedAmount, 2);
        }, '0.00');
    }

    protected function normalizeQuantity(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Cantidad invalida.');
        }

        return bcadd($value, '0', 6);
    }

    protected function roundMoney(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    protected function normalizeReason(?string $reason): string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Debes indicar un motivo para devolver la venta.');
        }

        return $reason;
    }

    protected function appendOperationalReason(?string $existingNotes, string $label, string $reason): string
    {
        $entry = '['.$label.'] '.$reason;
        $existingNotes = trim((string) $existingNotes);

        return $existingNotes === '' ? $entry : $existingNotes.PHP_EOL.$entry;
    }
}
