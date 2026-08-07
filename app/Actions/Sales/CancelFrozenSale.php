<?php

namespace App\Actions\Sales;

use App\Enums\FrozenSaleStatus;
use App\Models\Company;
use App\Models\FrozenSale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelFrozenSale
{
    public function handle(Company $company, FrozenSale $frozenSale): FrozenSale
    {
        if ($frozenSale->company_id !== $company->id) {
            throw new InvalidArgumentException('La venta congelada no pertenece a la empresa indicada.');
        }

        return DB::transaction(function () use ($frozenSale) {
            $frozenSale = FrozenSale::query()
                ->lockForUpdate()
                ->findOrFail($frozenSale->id);

            if ($frozenSale->status === FrozenSaleStatus::Converted->value) {
                throw new InvalidArgumentException('La venta congelada ya fue convertida.');
            }

            if ($frozenSale->status === FrozenSaleStatus::Expired->value) {
                throw new InvalidArgumentException('La venta congelada ya expiro.');
            }

            if ($frozenSale->status === FrozenSaleStatus::Cancelled->value) {
                return $frozenSale;
            }

            $frozenSale->update([
                'status' => FrozenSaleStatus::Cancelled->value,
            ]);

            return $frozenSale->fresh();
        });
    }
}
