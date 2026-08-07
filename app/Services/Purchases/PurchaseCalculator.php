<?php

namespace App\Services\Purchases;

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Services\Products\ProductPresentationConverter;
use InvalidArgumentException;

class PurchaseCalculator
{
    public function __construct(
        protected ProductPresentationConverter $presentationConverter,
    ) {
    }

    public function calculateLine(
        Product $product,
        ?ProductPresentation $presentation,
        ?ProductVariant $variant,
        string|int|float $quantity,
        string|int|float $unitCost,
        string|int|float $taxRate = 0,
    ): array {
        $quantity = $this->normalizeDecimal($quantity, 6);
        $unitCost = $this->normalizeDecimal($unitCost, 4);
        $taxRate = $this->normalizeDecimal($taxRate, 2);

        if (bccomp($quantity, '0', 6) <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        if (bccomp($unitCost, '0', 4) < 0) {
            throw new InvalidArgumentException('El costo unitario no puede ser negativo.');
        }

        if (bccomp($taxRate, '0', 2) < 0) {
            throw new InvalidArgumentException('La tasa de impuesto no puede ser negativa.');
        }

        $this->assertCatalogConsistency($product, $presentation, $variant);

        $baseQuantity = $presentation
            ? $this->presentationConverter->toBaseQuantity($quantity, (string) $presentation->conversion_factor, 6)
            : $quantity;

        $lineSubtotalRaw = bcmul($quantity, $unitCost, 6);
        $taxAmountRaw = bcdiv(bcmul($lineSubtotalRaw, $taxRate, 6), '100', 6);
        $lineTotalRaw = bcadd($lineSubtotalRaw, $taxAmountRaw, 6);

        return [
            'product_id' => $product->id,
            'product_presentation_id' => $presentation?->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
            'base_quantity' => $baseQuantity,
            'unit_cost' => $unitCost,
            'tax_rate' => $taxRate,
            'line_subtotal' => $this->roundMoney($lineSubtotalRaw),
            'tax_amount' => $this->roundMoney($taxAmountRaw),
            'line_total' => $this->roundMoney($lineTotalRaw),
        ];
    }

    public function calculateTotals(array $lines): array
    {
        $subtotal = '0.00';
        $taxTotal = '0.00';
        $total = '0.00';

        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line['line_subtotal'], 2);
            $taxTotal = bcadd($taxTotal, (string) $line['tax_amount'], 2);
            $total = bcadd($total, (string) $line['line_total'], 2);
        }

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }

    protected function assertCatalogConsistency(
        Product $product,
        ?ProductPresentation $presentation,
        ?ProductVariant $variant,
    ): void {
        if ($presentation && $presentation->product_id !== $product->id) {
            throw new InvalidArgumentException('La presentacion no pertenece al producto seleccionado.');
        }

        if ($variant && $variant->product_id !== $product->id) {
            throw new InvalidArgumentException('La variante no pertenece al producto seleccionado.');
        }
    }

    protected function normalizeDecimal(string|int|float $value, int $scale): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Decimal invalido.');
        }

        return bcadd($value, '0', $scale);
    }

    protected function roundMoney(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
