<?php

namespace App\Services\Printing;

use App\Models\Sale;
use App\Support\Money;
use App\Support\SaleTaxCalculator;

// Datos para la vista publica del QR del ticket (sales.receipt.public):
// a diferencia de BuildSaleTicketViewData, los nombres de producto NUNCA
// se abrevian (el usuario los ve desde su celular, sin la limitante del
// ancho de papel termico) y el orden es el mismo array $sale->items sin
// reordenar, para que coincida con el ticket que tiene en la mano.
class BuildPublicSaleReceiptViewData
{
    public function handle(Sale $sale): array
    {
        $sale->loadMissing(['items.product', 'items.presentation', 'items.variant', 'company']);

        return [
            'sale' => $sale,
            'company' => $sale->company,
            'items' => $sale->items->map(function ($item) {
                $description = $item->description_snapshot
                    ?: $item->product?->name
                    ?: 'Producto';

                if ($item->variant?->sku) {
                    $description .= ' / '.$item->variant->sku;
                }

                if ($item->presentation?->name) {
                    $description .= ' ('.$item->presentation->name.')';
                }

                return [
                    'description' => $description,
                    'quantity' => $this->formatQuantity((float) $item->quantity),
                    'unit_price' => Money::format((float) $item->unit_price),
                    'line_total' => Money::format((float) $item->line_total),
                ];
            })->all(),
            // Total menos el IVA ya incluido en cada producto (no
            // $sale->subtotal, que es la base ANTES del tax_rate aditivo de
            // la linea — en ventas POS ese siempre es 0, asi que sin este
            // ajuste el Subtotal quedaba igual al Total). Ver
            // SaleTaxCalculator::taxExcludedSubtotal().
            'subtotal' => Money::format((float) SaleTaxCalculator::taxExcludedSubtotal($sale)),
            'taxIncludedTotal' => Money::format((float) SaleTaxCalculator::includedTaxTotal($sale)),
            'grandTotal' => Money::format((float) $sale->grand_total),
        ];
    }

    protected function formatQuantity(float $quantity): string
    {
        $formatted = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
