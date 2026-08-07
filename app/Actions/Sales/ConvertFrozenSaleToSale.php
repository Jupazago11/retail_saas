<?php

namespace App\Actions\Sales;

use App\Enums\FrozenSaleStatus;
use App\Enums\SaleStatus;
use App\Models\Company;
use App\Models\FrozenSale;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConvertFrozenSaleToSale
{
    public function __construct(
        protected CreateSale $createSale,
    ) {
    }

    public function handle(Company $company, FrozenSale $frozenSale, array $overrides = []): Sale
    {
        if ($frozenSale->company_id !== $company->id) {
            throw new InvalidArgumentException('La venta congelada no pertenece a la empresa indicada.');
        }

        $frozenSale = $frozenSale->fresh();

        if ($this->isExpired($frozenSale) && $frozenSale->status === FrozenSaleStatus::Open->value) {
            $frozenSale->update([
                'status' => FrozenSaleStatus::Expired->value,
            ]);

            throw new InvalidArgumentException('La venta congelada ya expiro.');
        }

        return DB::transaction(function () use ($company, $frozenSale, $overrides) {
            $frozenSale = FrozenSale::query()
                ->lockForUpdate()
                ->findOrFail($frozenSale->id);

            $this->assertConvertible($frozenSale);

            $payload = $frozenSale->payload_snapshot;

            $sale = $this->createSale->handle($company, array_merge([
                'branch_id' => $frozenSale->branch_id,
                'warehouse_id' => $frozenSale->warehouse_id,
                'cash_register_id' => $frozenSale->cash_register_id,
                'customer_id' => $frozenSale->customer_id,
                'user_id' => $frozenSale->created_by,
                'sale_type' => $payload['sale_type'] ?? 'pos',
                'pricing_snapshot' => $payload['pricing_snapshot'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'status' => SaleStatus::Confirmed->value,
                'items' => $payload['items'] ?? [],
            ], $overrides));

            $frozenSale->update([
                'status' => FrozenSaleStatus::Converted->value,
                'converted_sale_id' => $sale->id,
            ]);

            return $sale;
        });
    }

    protected function assertConvertible(FrozenSale $frozenSale): void
    {
        if ($frozenSale->status === FrozenSaleStatus::Converted->value) {
            throw new InvalidArgumentException('La venta congelada ya fue convertida.');
        }

        if ($frozenSale->status === FrozenSaleStatus::Cancelled->value) {
            throw new InvalidArgumentException('La venta congelada ya fue cancelada.');
        }

        if ($this->isExpired($frozenSale)) {
            throw new InvalidArgumentException('La venta congelada ya expiro.');
        }
    }

    protected function isExpired(FrozenSale $frozenSale): bool
    {
        return $frozenSale->expires_at !== null && $frozenSale->expires_at->isPast();
    }
}
