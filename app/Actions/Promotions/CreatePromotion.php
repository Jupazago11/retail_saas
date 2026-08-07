<?php

namespace App\Actions\Promotions;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionTargetType;
use App\Enums\PromotionType;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\CompanyPlanResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreatePromotion
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected CompanyPlanResolver $companyPlanResolver,
    ) {
    }

    public function handle(Company $company, array $attributes): Promotion
    {
        $promotionType = PromotionType::from($attributes['promotion_type'] ?? '');
        $discountType = PromotionDiscountType::from($attributes['discount_type'] ?? '');
        $discountValue = $this->normalizeDecimal($attributes['discount_value'] ?? 0, 4);
        $name = $this->blankToNull($attributes['name'] ?? null);

        if (! $this->companyPlanResolver->hasModule($company, 'promotions')) {
            throw new InvalidArgumentException('El plan actual no tiene habilitado el modulo de promociones.');
        }

        if ($promotionType === PromotionType::ComboPrice && ! $this->companyPlanResolver->hasFeature($company, 'pos.combos')) {
            throw new InvalidArgumentException('El plan actual no tiene habilitada la feature de combos.');
        }

        if ($name === null) {
            throw new InvalidArgumentException('La promocion debe tener un nombre.');
        }

        if (bccomp($discountValue, '0.0000', 4) <= 0) {
            throw new InvalidArgumentException('El valor de la promocion debe ser mayor a cero.');
        }

        $targets = $attributes['targets'] ?? [];
        $comboItems = $attributes['combo_items'] ?? [];

        if ($promotionType === PromotionType::ProductDiscount && $targets === []) {
            throw new InvalidArgumentException('La promocion por producto requiere al menos un objetivo.');
        }

        if ($promotionType === PromotionType::ComboPrice && count($comboItems) < 2) {
            throw new InvalidArgumentException('El combo requiere al menos dos componentes.');
        }

        return DB::transaction(function () use ($company, $attributes, $promotionType, $discountType, $discountValue, $name, $targets, $comboItems) {
            $promotion = Promotion::query()->create([
                'company_id' => $company->id,
                'name' => $name,
                'code' => $this->blankToNull($attributes['code'] ?? null),
                'status' => $attributes['status'] ?? PromotionStatus::Active->value,
                'promotion_type' => $promotionType->value,
                'discount_type' => $discountType->value,
                'discount_value' => $discountValue,
                'priority' => (int) ($attributes['priority'] ?? 100),
                'starts_at' => $attributes['starts_at'] ?? null,
                'ends_at' => $attributes['ends_at'] ?? null,
            ]);

            if ($promotionType === PromotionType::ProductDiscount) {
                foreach ($targets as $target) {
                    $targetType = PromotionTargetType::from($target['target_type'] ?? '');
                    $targetId = (int) ($target['target_id'] ?? 0);

                    $this->assertTargetBelongsToCompany($company, $targetType, $targetId);

                    $promotion->targets()->create([
                        'target_type' => $targetType->value,
                        'target_id' => $targetId,
                        'min_quantity' => $this->normalizeDecimal($target['min_quantity'] ?? 1, 6),
                    ]);
                }
            }

            if ($promotionType === PromotionType::ComboPrice) {
                foreach ($comboItems as $comboItem) {
                    $product = Product::query()
                        ->where('company_id', $company->id)
                        ->findOrFail((int) ($comboItem['product_id'] ?? 0));

                    $variantId = $comboItem['product_variant_id'] ?? null;

                    if ($variantId) {
                        ProductVariant::query()
                            ->where('company_id', $company->id)
                            ->where('product_id', $product->id)
                            ->findOrFail((int) $variantId);
                    }

                    $promotion->comboItems()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variantId ? (int) $variantId : null,
                        'required_quantity' => $this->normalizeDecimal($comboItem['required_quantity'] ?? 1, 6),
                    ]);
                }
            }

            $promotion = $promotion->fresh(['targets', 'comboItems']);
            $this->auditLogger->logCreated($company, 'promotion.created', $promotion);

            return $promotion;
        });
    }

    protected function assertTargetBelongsToCompany(Company $company, PromotionTargetType $targetType, int $targetId): void
    {
        match ($targetType) {
            PromotionTargetType::Product => Product::query()->where('company_id', $company->id)->findOrFail($targetId),
            PromotionTargetType::Category => Category::query()->where('company_id', $company->id)->findOrFail($targetId),
            PromotionTargetType::Variant => ProductVariant::query()->where('company_id', $company->id)->findOrFail($targetId),
        };
    }

    protected function normalizeDecimal(mixed $value, int $scale): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Decimal invalido.');
        }

        return bcadd($value, '0', $scale);
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
