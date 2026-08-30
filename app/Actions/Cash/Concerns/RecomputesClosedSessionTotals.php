<?php

namespace App\Actions\Cash\Concerns;

use App\Enums\CashSessionStatus;
use App\Enums\PaymentStatus;
use App\Models\CashSession;

trait RecomputesClosedSessionTotals
{
    /**
     * Cuando un administrador corrige una base o un pago de caja de una
     * sesion ya cerrada, el esperado/la diferencia/el estado quedaron
     * desactualizados con el valor viejo. Los recalcula manteniendo fijo
     * lo que sí es un hecho historico: el monto que se conto ese dia.
     */
    protected function recomputeClosedTotalsIfNeeded(CashSession $cashSession): void
    {
        if (! in_array($cashSession->status, [CashSessionStatus::Closed->value, CashSessionStatus::Reconciled->value], true)) {
            return;
        }

        $cashSession->loadMissing(['payments.sale', 'expenses', 'funds']);

        // Igual que en CloseCashSession::handle(): un pago de venta
        // backdateada (sold_at de otro dia) no cuenta para el efectivo
        // esperado, porque ese dinero no entro fisicamente el dia de esta
        // sesion (ver Payment::belongsToCashSessionDay()).
        $cashPayments = $cashSession->payments
            ->where('status', PaymentStatus::Confirmed->value)
            ->where('payment_method_code', 'cash')
            ->filter(fn ($payment) => $payment->belongsToCashSessionDay($cashSession))
            ->sum('amount');

        $expenses = $cashSession->expenses->sum('amount');
        $fundsTotal = $cashSession->funds->sum('amount');

        $expected = bcadd((string) $fundsTotal, number_format((float) $cashPayments, 2, '.', ''), 2);
        $expected = bcsub($expected, number_format((float) $expenses, 2, '.', ''), 2);

        $counted = (string) ($cashSession->closing_counted_amount ?? '0.00');
        $difference = bcsub($counted, $expected, 2);
        $status = bccomp($difference, '0.00', 2) === 0
            ? CashSessionStatus::Reconciled->value
            : CashSessionStatus::Closed->value;

        $cashSession->update([
            'opening_amount' => $fundsTotal,
            'closing_expected_amount' => $expected,
            'difference_amount' => $difference,
            'status' => $status,
        ]);
    }
}
