<?php

namespace App\Actions\Sales;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Company;
use App\Models\Sale;
use App\Services\Audit\AuditLogger;
use App\Services\Credit\CreditLedger;
use App\Services\Inventory\ReturnSaleToInventory;
use App\Services\Loyalty\LoyaltyLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelSale
{
    public function __construct(
        protected ReturnSaleToInventory $returnSaleToInventory,
        protected CreditLedger $creditLedger,
        protected LoyaltyLedger $loyaltyLedger,
        protected AuditLogger $auditLogger,
    ) {}

    public function handle(Company $company, Sale $sale, ?string $reason = null): Sale
    {
        if ($sale->company_id !== $company->id) {
            throw new InvalidArgumentException('La venta no pertenece a la empresa indicada.');
        }

        $reason = $this->normalizeReason($reason);

        $beforeSale = $sale->fresh();

        $sale = DB::transaction(function () use ($sale, $reason) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items', 'payments'])
                ->findOrFail($sale->id);

            if ($sale->status === SaleStatus::Cancelled->value) {
                return $sale->fresh(['items', 'payments']);
            }

            if (in_array($sale->status, [
                SaleStatus::PartiallyReturned->value,
                SaleStatus::Returned->value,
            ], true)) {
                throw new InvalidArgumentException('No se puede anular una venta que ya tiene devoluciones registradas.');
            }

            if ($sale->status === SaleStatus::Draft->value) {
                $sale->update([
                    'status' => SaleStatus::Cancelled->value,
                    'cancelled_at' => now(),
                    'notes' => $this->appendOperationalReason($sale->notes, 'Anulacion', $reason),
                ]);

                return $sale->fresh(['items', 'payments']);
            }

            if ($sale->status !== SaleStatus::Confirmed->value) {
                throw new InvalidArgumentException('Solo se pueden anular ventas en borrador o confirmadas.');
            }

            $remainingItems = $sale->items
                ->map(fn ($item) => [
                    'sale_item_id' => $item->id,
                    'quantity' => bcsub((string) $item->quantity, (string) $item->returned_quantity, 6),
                ])
                ->filter(fn (array $item) => bccomp($item['quantity'], '0', 6) === 1)
                ->values()
                ->all();

            if ($remainingItems !== []) {
                $sale = $this->returnSaleToInventory->handle($sale, $remainingItems, SaleStatus::Cancelled);
            }

            if ($sale->creditAccount) {
                // No siempre es el grand_total: una venta POS puede tener
                // solo una porcion a credito (pago mixto), o ya haber
                // recibido una devolucion parcial que redujo lo pendiente.
                $outstanding = $this->creditLedger->outstandingForSale($sale);

                if (bccomp($outstanding, '0.00', 2) > 0) {
                    $this->creditLedger->recordSaleCancellationAdjustment(
                        $sale->creditAccount,
                        $sale,
                        $outstanding
                    );
                }
            }

            if ($sale->customer?->loyaltyAccount) {
                $this->loyaltyLedger->restoreRedeemedPointsForSaleCancellation($sale->customer->loyaltyAccount, $sale);
                $this->loyaltyLedger->reverseForSaleCancellation($sale->customer->loyaltyAccount, $sale);
            }

            $sale->payments()
                ->where('status', PaymentStatus::Confirmed->value)
                ->update([
                    'status' => PaymentStatus::Reversed->value,
                ]);

            $sale->update([
                'status' => SaleStatus::Cancelled->value,
                'cancelled_at' => now(),
                'notes' => $this->appendOperationalReason($sale->notes, 'Anulacion', $reason),
            ]);

            return $sale->fresh(['items', 'payments']);
        });

        $this->auditLogger->logUpdated($company, 'sale.cancelled', $beforeSale, $sale);

        return $sale;
    }

    protected function normalizeReason(?string $reason): string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Debes indicar un motivo para anular la venta.');
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
