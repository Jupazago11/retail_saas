<?php

namespace App\Actions\Sales;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\Credit\CreditAccountResolver;
use App\Services\Credit\CreditLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateDraftPosSale
{
    public function __construct(
        protected UpdateDraftSale $updateDraftSale,
        protected RegisterSalePayments $registerSalePayments,
        protected CreditAccountResolver $creditAccountResolver,
        protected CreditLedger $creditLedger,
    ) {}

    public function handle(Company $company, Sale $sale, array $saleAttributes, array $paymentAttributes = []): Sale
    {
        return DB::transaction(function () use ($company, $sale, $saleAttributes, $paymentAttributes) {
            $sale = $this->updateDraftSale->handle($company, $sale, $saleAttributes);

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

            $creditAmount = $this->sumCreditAmount($preparedPayments);

            if (bccomp($creditAmount, '0', 2) > 0) {
                $sale = $this->chargeCreditPortion($company, $sale, $creditAmount);
            }

            $this->registerSalePayments->handle($company, $sale, [
                'cash_session_id' => $paymentAttributes['cash_session_id'] ?? null,
                'received_by' => $paymentAttributes['received_by'] ?? null,
                'payments' => $preparedPayments,
            ]);

            return $sale->fresh(['payments']);
        });
    }

    /**
     * Mismo criterio que CreatePosSale::chargeCreditPortion(): la porcion
     * "credit" del cobro inmediato es deuda real del cliente, asi que se
     * carga al ledger de credito (`credit_movements`) en vez de quedar solo
     * como una etiqueta de pago.
     */
    protected function chargeCreditPortion(Company $company, Sale $sale, string $creditAmount): Sale
    {
        if (! $sale->customer_id) {
            throw new InvalidArgumentException('Para pagar con credito debes seleccionar un cliente.');
        }

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->findOrFail($sale->customer_id);

        $creditAccount = $this->creditAccountResolver->resolveActiveAccount($company, $customer);

        $sale->update([
            'credit_account_id' => $creditAccount->id,
            'credit_due_at' => $sale->credit_due_at
                ?? now()->addDays($this->creditAccountResolver->resolveTermDays($company, $creditAccount)),
        ]);

        $this->creditLedger->recordSaleCharge($creditAccount, $sale, $creditAmount);

        return $sale->fresh();
    }

    protected function sumCreditAmount(array $payments): string
    {
        return collect($payments)
            ->filter(fn (array $payment) => ($payment['payment_method_code'] ?? '') === 'credit')
            ->reduce(fn (string $carry, array $payment) => bcadd($carry, (string) $payment['amount'], 2), '0.00');
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
