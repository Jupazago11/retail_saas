<?php

namespace App\Actions\Sales;

use App\Enums\FrozenSaleStatus;
use App\Models\Company;
use App\Models\FrozenSale;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Services\Sales\SaleCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Agrega una linea nueva a una FrozenSale abierta, sin tocar las que ya
 * tenia. CreateFrozenSale no sirve para esto: siempre crea una fila nueva.
 * Pensado para "comanda abierta" (Mesas y Comandas), donde el mesero agrega
 * platos de a uno sobre la misma cuenta a lo largo de la sesion en mesa.
 */
class UpdateFrozenSaleItems
{
    public function __construct(
        protected SaleCalculator $saleCalculator,
    ) {
    }

    public function handle(Company $company, FrozenSale $frozenSale, array $newItem): FrozenSale
    {
        if ($frozenSale->company_id !== $company->id) {
            throw new InvalidArgumentException('La venta congelada no pertenece a la empresa indicada.');
        }

        return DB::transaction(function () use ($company, $frozenSale, $newItem) {
            $frozenSale = FrozenSale::query()->lockForUpdate()->findOrFail($frozenSale->id);

            if ($frozenSale->status !== FrozenSaleStatus::Open->value) {
                throw new InvalidArgumentException('Solo se pueden agregar platos a una comanda abierta.');
            }

            $product = $this->resolveProduct($company, (int) ($newItem['product_id'] ?? 0));
            $presentation = $this->resolvePresentation($company, $product, $newItem['product_presentation_id'] ?? null);
            $variant = $this->resolveVariant($company, $product, $newItem['product_variant_id'] ?? null);

            $calculatedLine = $this->saleCalculator->calculateLine(
                $product,
                $presentation,
                $variant,
                $newItem['quantity'] ?? 0,
                $newItem['unit_price'] ?? $product->price_1,
                $newItem['discount_amount'] ?? 0,
                $newItem['tax_rate'] ?? 0,
            );

            $payload = $frozenSale->payload_snapshot;
            $lines = array_merge($payload['items'] ?? [], [$calculatedLine]);
            $payload['items'] = $lines;
            $payload['totals'] = $this->saleCalculator->calculateTotals($lines);

            $frozenSale->update(['payload_snapshot' => $payload]);

            return $frozenSale->fresh();
        });
    }

    protected function resolveProduct(Company $company, int $productId): Product
    {
        return Product::query()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->findOrFail($productId);
    }

    protected function resolvePresentation(Company $company, Product $product, mixed $presentationId): ?ProductPresentation
    {
        if (! $presentationId) {
            return null;
        }

        return ProductPresentation::query()
            ->where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->whereNull('deleted_at')
            ->findOrFail((int) $presentationId);
    }

    protected function resolveVariant(Company $company, Product $product, mixed $variantId): ?ProductVariant
    {
        if (! $variantId) {
            return null;
        }

        return ProductVariant::query()
            ->where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->findOrFail((int) $variantId);
    }
}
