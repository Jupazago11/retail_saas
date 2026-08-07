<?php

namespace App\Services\Sales;

use App\Models\Company;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Services\Loyalty\LoyaltyLedger;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalePreviewService
{
    public function __construct(
        protected SaleCalculator $saleCalculator,
        protected PromotionEngine $promotionEngine,
        protected LoyaltyLedger $loyaltyLedger,
        protected CompanyPlanResolver $companyPlanResolver,
    ) {
    }

    public function preview(Company $company, array $attributes): array
    {
        $resolvedItems = collect($attributes['items'] ?? [])
            ->map(fn (array $item) => $this->resolvePreviewItem($company, $item))
            ->filter()
            ->values()
            ->all();

        if ($resolvedItems === []) {
            return [
                'lines' => [],
                'totals' => [
                    'subtotal' => '0.00',
                    'discount_total' => '0.00',
                    'tax_total' => '0.00',
                    'grand_total' => '0.00',
                ],
                'promotions' => [],
                'loyalty_redemption' => null,
                'warnings' => [],
            ];
        }

        $promotionPayload = $this->promotionEngine->apply($company, $resolvedItems, $attributes['sold_at'] ?? null);
        $calculatedLines = $this->calculateLines($promotionPayload['items']);
        $totals = $this->saleCalculator->calculateTotals($calculatedLines);
        $customer = $this->resolveCustomer($company, $attributes['customer_id'] ?? null);
        $loyaltyAccount = $this->resolveLoyaltyAccount($customer);
        $warnings = [];

        $loyaltyRedemption = $this->resolveLoyaltyRedemptionPreview(
            $company,
            $loyaltyAccount,
            $attributes['loyalty_points_to_redeem'] ?? null,
            $calculatedLines,
            $totals,
            $warnings,
        );

        if ($loyaltyRedemption !== null) {
            $promotionPayload['items'] = collect($promotionPayload['items'])
                ->map(function (array $item, int $index) use ($loyaltyRedemption) {
                    $item['loyalty_discount_amount'] = $loyaltyRedemption['line_discounts'][$index];

                    return $item;
                })
                ->all();

            $calculatedLines = $this->calculateLines($promotionPayload['items']);
            $totals = $this->saleCalculator->calculateTotals($calculatedLines);
        }

        return [
            'lines' => $calculatedLines,
            'totals' => $totals,
            'promotions' => $promotionPayload['summary'],
            'loyalty_redemption' => $loyaltyRedemption !== null ? [
                'points' => $loyaltyRedemption['points'],
                'cash_equivalent' => $loyaltyRedemption['cash_equivalent'],
                'rate' => $loyaltyRedemption['rate'],
                'rule_type' => $loyaltyRedemption['rule_type'],
            ] : null,
            'warnings' => $warnings,
        ];
    }

    protected function resolvePreviewItem(Company $company, array $item): ?array
    {
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity = trim((string) ($item['quantity'] ?? ''));
        $unitPrice = trim((string) ($item['unit_price'] ?? ''));

        if ($productId <= 0 || $quantity === '' || $unitPrice === '') {
            return null;
        }

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $quantity) || ! preg_match('/^-?\d+(?:\.\d+)?$/', $unitPrice)) {
            return null;
        }

        if (bccomp($quantity, '0', 6) !== 1 || bccomp($unitPrice, '0', 4) === -1) {
            return null;
        }

        $product = Product::query()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->find($productId);

        if (! $product) {
            return null;
        }

        $presentation = null;

        if (! empty($item['product_presentation_id'])) {
            $presentation = ProductPresentation::query()
                ->where('company_id', $company->id)
                ->where('product_id', $product->id)
                ->whereNull('deleted_at')
                ->find((int) $item['product_presentation_id']);
        }

        $variant = null;

        if (! empty($item['product_variant_id'])) {
            $variant = ProductVariant::query()
                ->where('company_id', $company->id)
                ->where('product_id', $product->id)
                ->find((int) $item['product_variant_id']);
        }

        return [
            'product' => $product,
            'presentation' => $presentation,
            'variant' => $variant,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $item['discount_amount'] ?? 0,
            'tax_rate' => $item['tax_rate'] ?? 0,
        ];
    }

    protected function calculateLines(array $items): array
    {
        return collect($items)
            ->map(function (array $item) {
                $item['product']->setAttribute('__promotion_snapshot', $item['promotion_snapshot'] !== [] ? $item['promotion_snapshot'] : null);

                return $this->saleCalculator->calculateLine(
                    $item['product'],
                    $item['presentation'],
                    $item['variant'],
                    $item['quantity'],
                    $item['unit_price'],
                    bcadd(
                        bcadd((string) $item['discount_amount'], (string) $item['promotion_discount_amount'], 2),
                        (string) ($item['loyalty_discount_amount'] ?? '0.00'),
                        2,
                    ),
                    $item['tax_rate'],
                );
            })
            ->all();
    }

    protected function resolveCustomer(Company $company, mixed $customerId): ?Customer
    {
        if (! $customerId) {
            return null;
        }

        return Customer::query()
            ->where('company_id', $company->id)
            ->with('loyaltyAccount')
            ->find((int) $customerId);
    }

    protected function resolveLoyaltyAccount(?Customer $customer): ?LoyaltyAccount
    {
        if (! $customer || ! $customer->loyalty_enabled) {
            return null;
        }

        return $customer->loyaltyAccount;
    }

    protected function resolveLoyaltyRedemptionPreview(
        Company $company,
        ?LoyaltyAccount $loyaltyAccount,
        mixed $requestedPoints,
        array $calculatedLines,
        array $totals,
        array &$warnings,
    ): ?array {
        if ($requestedPoints === null || trim((string) $requestedPoints) === '') {
            return null;
        }

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', trim((string) $requestedPoints))) {
            $warnings[] = 'La redencion de puntos no es valida.';

            return null;
        }

        $requestedPoints = bcadd(trim((string) $requestedPoints), '0', 4);

        if (bccomp($requestedPoints, '0.0000', 4) !== 1) {
            return null;
        }

        if (! $this->companyPlanResolver->hasFeature($company, 'loyalty.enabled')) {
            $warnings[] = 'El plan actual no tiene habilitada la feature de fidelizacion.';

            return null;
        }

        if (! $loyaltyAccount) {
            $warnings[] = 'Selecciona un cliente con cuenta de fidelizacion activa para redimir puntos.';

            return null;
        }

        if (bccomp((string) $totals['grand_total'], '0.00', 2) !== 1) {
            return null;
        }

        if (bccomp($requestedPoints, (string) $loyaltyAccount->points_balance, 4) === 1) {
            $warnings[] = 'La cuenta de fidelizacion no tiene puntos suficientes para esta redencion.';

            return null;
        }

        $requestedCashEquivalent = $this->loyaltyLedger->calculateCashEquivalentForPoints($company, $requestedPoints);
        $appliedCashEquivalent = bccomp($requestedCashEquivalent, (string) $totals['grand_total'], 2) === 1
            ? (string) $totals['grand_total']
            : $requestedCashEquivalent;
        $appliedPoints = $this->loyaltyLedger->calculatePointsForCashEquivalent($company, $appliedCashEquivalent);

        if (bccomp($appliedPoints, '0.0000', 4) <= 0 || bccomp($appliedCashEquivalent, '0.00', 2) <= 0) {
            return null;
        }

        return [
            'points' => $appliedPoints,
            'cash_equivalent' => $appliedCashEquivalent,
            'line_discounts' => $this->allocateLoyaltyDiscountAcrossLines($calculatedLines, $appliedCashEquivalent),
            'rate' => $this->loyaltyLedger->calculatePointsForCashEquivalent($company, '1.00'),
            'rule_type' => 'per_currency',
        ];
    }

    protected function allocateLoyaltyDiscountAcrossLines(array $calculatedLines, string $cashEquivalent): array
    {
        $bases = collect($calculatedLines)->map(function (array $line) {
            return bcsub((string) $line['line_subtotal'], (string) $line['discount_amount'], 2);
        });

        $totalBase = $bases->reduce(fn (string $carry, string $value) => bcadd($carry, $value, 2), '0.00');

        if (bccomp($totalBase, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('No se puede distribuir la redencion de puntos sobre lineas sin base gravable.');
        }

        $positiveIndexes = $bases->keys()->filter(fn (int $index) => bccomp($bases[$index], '0.00', 2) === 1)->values()->all();
        $remaining = $cashEquivalent;
        $allocations = array_fill(0, count($calculatedLines), '0.00');

        foreach ($positiveIndexes as $position => $index) {
            if ($position === array_key_last($positiveIndexes)) {
                $allocations[$index] = $remaining;
                break;
            }

            $raw = bcdiv(bcmul($cashEquivalent, $bases[$index], 6), $totalBase, 6);
            $allocated = $this->roundMoney($raw);

            if (bccomp($allocated, $remaining, 2) === 1) {
                $allocated = $remaining;
            }

            $allocations[$index] = $allocated;
            $remaining = bcsub($remaining, $allocated, 2);
        }

        return $allocations;
    }

    protected function roundMoney(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
