<?php

namespace App\Actions\Cash\Concerns;

trait ResolvesDenominationBreakdown
{
    /**
     * Compartido entre CloseCashSession (primer cierre) y
     * UpdateCashSessionCount (corregir el contado de una sesion ya
     * cerrada) — ambas aceptan el mismo desglose por denominacion y lo
     * validan igual.
     */
    protected function resolveDenominationBreakdown(array $attributes): ?array
    {
        if (! array_key_exists('denomination_breakdown', $attributes) || ! is_array($attributes['denomination_breakdown'])) {
            return null;
        }

        $breakdown = [];

        foreach ($attributes['denomination_breakdown'] as $row) {
            $value = (int) ($row['value'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($value <= 0 || $quantity <= 0) {
                continue;
            }

            $breakdown[] = ['value' => $value, 'quantity' => $quantity];
        }

        return $breakdown;
    }

    protected function amountFromDenominationBreakdown(array $breakdown): string
    {
        return array_reduce(
            $breakdown,
            fn (string $carry, array $row) => bcadd($carry, bcmul((string) $row['value'], (string) $row['quantity'], 2), 2),
            '0.00',
        );
    }
}
