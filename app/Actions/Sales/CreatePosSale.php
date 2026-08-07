<?php

namespace App\Actions\Sales;

use App\Models\Company;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreatePosSale
{
    public function __construct(
        protected CreateSale $createSale,
        protected RegisterSalePayments $registerSalePayments,
    ) {
    }

    public function handle(Company $company, array $saleAttributes, array $paymentAttributes = []): Sale
    {
        return DB::transaction(function () use ($company, $saleAttributes, $paymentAttributes) {
            $sale = $this->createSale->handle($company, $saleAttributes);

            if (($paymentAttributes['require_immediate_payment'] ?? false) !== true) {
                return $sale->fresh(['payments']);
            }

            if ($sale->sale_type !== 'pos' || $sale->status !== 'confirmed') {
                throw new InvalidArgumentException('Solo se pueden registrar pagos inmediatos sobre ventas POS confirmadas.');
            }

            $payments = $paymentAttributes['payments'] ?? [];

            if (! is_array($payments) || $payments === []) {
                throw new InvalidArgumentException('Debes registrar al menos un pago inmediato para confirmar la venta POS.');
            }

            $preparedPayments = $this->preparePayments($sale, $payments);

            $this->registerSalePayments->handle($company, $sale, [
                'cash_session_id' => $paymentAttributes['cash_session_id'] ?? null,
                'received_by' => $paymentAttributes['received_by'] ?? null,
                'payments' => $preparedPayments,
            ]);

            return $sale->fresh(['payments']);
        });
    }

    protected function preparePayments(Sale $sale, array $payments): array
    {
        if (count($payments) === 1) {
            $payment = $payments[0];

            if ($this->blankToNull($payment['amount'] ?? null) === null) {
                $payment['amount'] = (string) $sale->grand_total;
            }

            return [$payment];
        }

        foreach ($payments as $index => $payment) {
            if ($this->blankToNull($payment['amount'] ?? null) === null) {
                throw new InvalidArgumentException('Cada pago inmediato debe tener monto cuando registras mas de un metodo.');
            }

            $payments[$index]['amount'] = trim((string) $payment['amount']);
        }

        return $payments;
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
