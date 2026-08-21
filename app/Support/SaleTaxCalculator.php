<?php

namespace App\Support;

use App\Models\Sale;

// El IVA de una venta va incluido en cada precio (no se suma aparte): esta
// clase solo desglosa cuanto de lo ya cobrado es impuesto, sumando por
// item su propio tax_rate (el IVA con el que se creo ESE producto, no un
// IVA unico para toda la venta). Compartido entre el ticket imprimible y
// el recibo publico (QR) para que ambos muestren siempre el mismo numero.
class SaleTaxCalculator
{
    public static function includedTaxTotal(Sale $sale): string
    {
        return $sale->items->reduce(
            fn (string $carry, $item) => bcadd($carry, self::includedTaxAmount(
                (string) $item->line_total,
                (string) ($item->product_tax_rate ?? '0'),
            ), 2),
            '0.00',
        );
    }

    // Base gravable (sin IVA) para mostrar en el ticket/recibo como
    // "Subtotal": grand_total ya trae el IVA de cada producto incluido, asi
    // que restarle el desglose de includedTaxTotal() es lo que deja
    // Subtotal + Impuestos = Total cuadrando visualmente. OJO: distinto de
    // $sale->subtotal (la base ANTES del tax_rate aditivo de la linea, que
    // en ventas POS siempre es 0 — ver comentario en SaleCalculator/ticket).
    public static function taxExcludedSubtotal(Sale $sale): string
    {
        return bcsub((string) $sale->grand_total, self::includedTaxTotal($sale), 2);
    }

    public static function includedTaxAmount(string $amount, string $ratePercent): string
    {
        if (bccomp($ratePercent, '0', 2) <= 0) {
            return '0.00';
        }

        $divisor = bcadd('1', bcdiv($ratePercent, '100', 6), 6);
        $net = bcdiv($amount, $divisor, 6);

        return number_format((float) bcsub($amount, $net, 6), 2, '.', '');
    }
}
