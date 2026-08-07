<?php

namespace App\Services\Promotions;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionTargetType;
use App\Enums\PromotionType;
use App\Models\Company;
use App\Models\Promotion;
use App\Models\PromotionComboItem;
use App\Models\PromotionTarget;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PromotionEngine
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected CompanyPlanResolver $companyPlanResolver,
    ) {
    }

    public function apply(Company $company, array $items, mixed $soldAt = null): array
    {
        if ($items === []) {
            return ['items' => [], 'summary' => []];
        }

        $workingItems = array_values(array_map(function (array $item, int $index) {
            $quantity = $this->normalizeDecimal($item['quantity'] ?? 0, 6);
            $manualDiscount = $this->normalizeDecimal($item['discount_amount'] ?? 0, 2);

            return [
                ...$item,
                'index' => $index,
                'quantity' => $quantity,
                'discount_amount' => $manualDiscount,
                'promotion_discount_amount' => '0.00',
                'promotion_snapshot' => [],
                'available_for_combo' => $quantity,
                'available_for_product_promotions' => $quantity,
            ];
        }, array_values($items), array_keys(array_values($items))));

        if (! $this->companyPlanResolver->hasModule($company, 'promotions')) {
            return [
                'items' => array_map(function (array $item) {
                    unset($item['available_for_combo'], $item['available_for_product_promotions'], $item['index']);
                    $item['promotion_snapshot'] = null;

                    return $item;
                }, $workingItems),
                'summary' => [],
            ];
        }

        $effectiveAt = $soldAt ? now()->parse($soldAt) : now();
        $allowStacking = (bool) $this->companySettings->get($company, 'pos', 'allow_promotion_stacking');
        $promotions = Promotion::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where(function ($query) use ($effectiveAt) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $effectiveAt);
            })
            ->where(function ($query) use ($effectiveAt) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $effectiveAt);
            })
            ->with(['targets', 'comboItems'])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
        $combosEnabled = $this->companyPlanResolver->hasFeature($company, 'pos.combos');

        $summary = [];

        foreach ($promotions as $promotion) {
            if ($promotion->promotion_type === PromotionType::ComboPrice->value) {
                if (! $combosEnabled) {
                    continue;
                }

                $applied = $this->applyComboPromotion($workingItems, $promotion, $allowStacking);

                if ($applied !== null) {
                    $summary[] = $applied;
                }
            }
        }

        foreach ($promotions as $promotion) {
            if ($promotion->promotion_type === PromotionType::ProductDiscount->value) {
                $applied = $this->applyProductPromotion($workingItems, $promotion);

                if ($applied !== null) {
                    $summary[] = $applied;
                }
            }
        }

        return [
            'items' => array_map(function (array $item) {
                unset($item['available_for_combo'], $item['available_for_product_promotions'], $item['index']);
                $item['promotion_snapshot'] = $item['promotion_snapshot'] !== [] ? $item['promotion_snapshot'] : null;

                return $item;
            }, $workingItems),
            'summary' => $summary,
        ];
    }

    protected function applyProductPromotion(array &$workingItems, Promotion $promotion): ?array
    {
        $appliedDiscount = '0.00';
        $affectedLines = [];

        foreach ($workingItems as $index => $item) {
            $matchedTarget = $promotion->targets->first(fn (PromotionTarget $target) => $this->matchesTarget($item, $target));

            if (! $matchedTarget) {
                continue;
            }

            if (bccomp($item['available_for_product_promotions'], (string) $matchedTarget->min_quantity, 6) === -1) {
                continue;
            }

            $applicableSubtotal = $this->roundMoney(bcmul($item['available_for_product_promotions'], $item['unit_price'], 6));
            $discount = $this->calculateProductPromotionDiscount($promotion, $applicableSubtotal);
            $remainingSubtotal = bcsub($applicableSubtotal, $item['promotion_discount_amount'], 2);

            if (bccomp($discount, $remainingSubtotal, 2) === 1) {
                $discount = $remainingSubtotal;
            }

            if (bccomp($discount, '0.00', 2) <= 0) {
                continue;
            }

            $item['promotion_discount_amount'] = bcadd($item['promotion_discount_amount'], $discount, 2);
            $item['promotion_snapshot'][] = [
                'promotion_id' => $promotion->id,
                'promotion_name' => $promotion->name,
                'promotion_type' => $promotion->promotion_type,
                'discount_type' => $promotion->discount_type,
                'discount_amount' => $discount,
                'affected_quantity' => $item['available_for_product_promotions'],
            ];
            $workingItems[$index] = $item;
            $appliedDiscount = bcadd($appliedDiscount, $discount, 2);
            $affectedLines[] = $index;
        }

        if ($affectedLines === []) {
            return null;
        }

        return [
            'promotion_id' => $promotion->id,
            'promotion_name' => $promotion->name,
            'promotion_type' => $promotion->promotion_type,
            'discount_amount' => $appliedDiscount,
            'affected_lines' => $affectedLines,
        ];
    }

    protected function applyComboPromotion(array &$workingItems, Promotion $promotion, bool $allowStacking): ?array
    {
        $allocations = $this->collectComboAllocations($workingItems, $promotion->comboItems);

        if ($allocations === null) {
            return null;
        }

        $comboCount = $allocations['combo_count'];
        $portions = $allocations['portions'];
        $regularSubtotal = $allocations['regular_subtotal'];
        $comboPrice = bcmul((string) $promotion->discount_value, $comboCount, 2);

        if (bccomp($regularSubtotal, $comboPrice, 2) <= 0) {
            return null;
        }

        $discountTotal = bcsub($regularSubtotal, $comboPrice, 2);
        $remainingDiscount = $discountTotal;
        $affectedLines = [];

        foreach ($portions as $portionIndex => $portion) {
            $isLast = $portionIndex === array_key_last($portions);
            $discount = $isLast
                ? $remainingDiscount
                : $this->roundMoney(
                    bcdiv(
                        bcmul($discountTotal, $portion['subtotal'], 6),
                        $regularSubtotal,
                        6
                    )
                );

            $lineIndex = $portion['line_index'];
            $lineItem = $workingItems[$lineIndex];
            $lineItem['promotion_discount_amount'] = bcadd(
                $lineItem['promotion_discount_amount'],
                $discount,
                2
            );
            $lineItem['promotion_snapshot'][] = [
                'promotion_id' => $promotion->id,
                'promotion_name' => $promotion->name,
                'promotion_type' => $promotion->promotion_type,
                'discount_type' => $promotion->discount_type,
                'discount_amount' => $discount,
                'affected_quantity' => $portion['quantity'],
            ];
            $lineItem['available_for_combo'] = bcsub(
                $lineItem['available_for_combo'],
                $portion['quantity'],
                6
            );

            if (! $allowStacking) {
                $lineItem['available_for_product_promotions'] = bcsub(
                    $lineItem['available_for_product_promotions'],
                    $portion['quantity'],
                    6
                );
            }

            $workingItems[$lineIndex] = $lineItem;
            $remainingDiscount = bcsub($remainingDiscount, $discount, 2);
            $affectedLines[] = $lineIndex;
        }

        return [
            'promotion_id' => $promotion->id,
            'promotion_name' => $promotion->name,
            'promotion_type' => $promotion->promotion_type,
            'discount_amount' => $discountTotal,
            'affected_lines' => array_values(array_unique($affectedLines)),
            'combo_count' => $comboCount,
        ];
    }

    protected function collectComboAllocations(array $workingItems, Collection $comboItems): ?array
    {
        $maxComboCount = null;

        foreach ($comboItems as $comboItem) {
            $available = collect($workingItems)
                ->filter(fn (array $item) => $this->matchesComboItem($item, $comboItem))
                ->reduce(fn (string $carry, array $item) => bcadd($carry, $item['available_for_combo'], 6), '0.000000');

            if (bccomp($available, (string) $comboItem->required_quantity, 6) === -1) {
                return null;
            }

            $possible = $this->floorDivision($available, (string) $comboItem->required_quantity, 6);
            $maxComboCount = $maxComboCount === null ? $possible : min($maxComboCount, $possible);
        }

        if ($maxComboCount === null || $maxComboCount <= 0) {
            return null;
        }

        $portions = [];
        $regularSubtotal = '0.00';

        foreach ($comboItems as $comboItem) {
            $remaining = bcmul((string) $comboItem->required_quantity, (string) $maxComboCount, 6);

            foreach ($workingItems as $index => $item) {
                if (! $this->matchesComboItem($item, $comboItem)) {
                    continue;
                }

                if (bccomp($remaining, '0', 6) <= 0) {
                    break;
                }

                if (bccomp($item['available_for_combo'], '0', 6) <= 0) {
                    continue;
                }

                $takenQuantity = bccomp($item['available_for_combo'], $remaining, 6) === 1
                    ? $remaining
                    : $item['available_for_combo'];

                $subtotal = $this->roundMoney(bcmul($takenQuantity, $item['unit_price'], 6));

                $portions[] = [
                    'line_index' => $index,
                    'quantity' => $takenQuantity,
                    'subtotal' => $subtotal,
                ];
                $regularSubtotal = bcadd($regularSubtotal, $subtotal, 2);
                $remaining = bcsub($remaining, $takenQuantity, 6);
            }

            if (bccomp($remaining, '0', 6) === 1) {
                return null;
            }
        }

        return [
            'combo_count' => $maxComboCount,
            'portions' => $portions,
            'regular_subtotal' => $regularSubtotal,
        ];
    }

    protected function calculateProductPromotionDiscount(Promotion $promotion, string $applicableSubtotal): string
    {
        return match ($promotion->discount_type) {
            PromotionDiscountType::Percentage->value => $this->roundMoney(
                bcdiv(
                    bcmul($applicableSubtotal, (string) $promotion->discount_value, 6),
                    '100',
                    6
                )
            ),
            PromotionDiscountType::FixedAmount->value => $this->roundMoney((string) $promotion->discount_value),
            default => throw new InvalidArgumentException('Tipo de descuento no soportado para promocion por producto.'),
        };
    }

    protected function matchesTarget(array $item, PromotionTarget $target): bool
    {
        return match ($target->target_type) {
            PromotionTargetType::Product->value => $item['product']->id === $target->target_id,
            PromotionTargetType::Category->value => $item['product']->category_id === $target->target_id,
            PromotionTargetType::Variant->value => $item['variant']?->id === $target->target_id,
            default => false,
        };
    }

    protected function matchesComboItem(array $item, PromotionComboItem $comboItem): bool
    {
        if ($item['product']->id !== $comboItem->product_id) {
            return false;
        }

        if ($comboItem->product_variant_id === null) {
            return true;
        }

        return $item['variant']?->id === $comboItem->product_variant_id;
    }

    protected function floorDivision(string $left, string $right, int $scale): int
    {
        return (int) floor((float) bcdiv($left, $right, $scale));
    }

    protected function normalizeDecimal(mixed $value, int $scale): string
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
