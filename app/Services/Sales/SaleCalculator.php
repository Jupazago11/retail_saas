<?php

namespace App\Services\Sales;

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Services\Products\ProductPresentationConverter;
use InvalidArgumentException;

class SaleCalculator
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
        string|int|float $unitPrice,
        string|int|float $discountAmount = 0,
        string|int|float $taxRate = 0,
    ): array {
        $quantity = $this->normalizeDecimal($quantity, 6);
        $unitPrice = $this->normalizeDecimal($unitPrice, 4);
        $discountAmount = $this->normalizeDecimal($discountAmount, 2);
        $taxRate = $this->normalizeDecimal($taxRate, 2);

        if (bccomp($quantity, '0', 6) <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        if (bccomp($unitPrice, '0', 4) < 0) {
            throw new InvalidArgumentException('El precio unitario no puede ser negativo.');
        }

        if (bccomp($discountAmount, '0', 2) < 0) {
            throw new InvalidArgumentException('El descuento no puede ser negativo.');
        }

        if (bccomp($taxRate, '0', 2) < 0) {
            throw new InvalidArgumentException('La tasa de impuesto no puede ser negativa.');
        }

        $this->assertCatalogConsistency($product, $presentation, $variant);

        $baseQuantity = $presentation
            ? $this->presentationConverter->toBaseQuantity($quantity, (string) $presentation->conversion_factor, 6)
            : $quantity;

        $lineSubtotalRaw = bcmul($quantity, $unitPrice, 6);

        if (bccomp($discountAmount, $lineSubtotalRaw, 2) === 1) {
            throw new InvalidArgumentException('El descuento no puede superar el subtotal de la linea.');
        }

        $taxableBase = bcsub($lineSubtotalRaw, $discountAmount, 6);
        $taxAmountRaw = bcdiv(bcmul($taxableBase, $taxRate, 6), '100', 6);
        $lineTotalRaw = bcadd($taxableBase, $taxAmountRaw, 6);

        return [
            'product_id' => $product->id,
            'product_presentation_id' => $presentation?->id,
            'product_variant_id' => $variant?->id,
            'description_snapshot' => $this->buildDescriptionSnapshot($product, $presentation, $variant),
            'promotion_snapshot' => $product->getAttribute('__promotion_snapshot'),
            'quantity' => $quantity,
            'base_quantity' => $baseQuantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $this->roundMoney($taxAmountRaw),
            'line_subtotal' => $this->roundMoney($lineSubtotalRaw),
            'line_total' => $this->roundMoney($lineTotalRaw),
            'cost_snapshot' => null,
        ];
    }

    public function calculateTotals(array $lines): array
    {
        $subtotal = '0.00';
        $discountTotal = '0.00';
        $taxTotal = '0.00';
        $grandTotal = '0.00';

        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line['line_subtotal'], 2);
            $discountTotal = bcadd($discountTotal, (string) $line['discount_amount'], 2);
            $taxTotal = bcadd($taxTotal, (string) $line['tax_amount'], 2);
            $grandTotal = bcadd($grandTotal, (string) $line['line_total'], 2);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
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

    protected function buildDescriptionSnapshot(
        Product $product,
        ?ProductPresentation $presentation,
        ?ProductVariant $variant,
    ): string {
        $parts = [$product->name];

        if ($presentation) {
            $parts[] = $presentation->name;
        }

        if ($variant) {
            $parts[] = $variant->sku ?: (string) $variant->id;
        }

        return implode(' - ', $parts);
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
