<?php

namespace App\Actions\Dining;

use App\Actions\Sales\ConvertFrozenSaleToSale;
use App\Models\Company;
use App\Models\DiningTable;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cobra la comanda abierta de una mesa (la convierte en Sale real via el
 * mismo motor de siempre) y libera la mesa.
 */
class CloseDiningTable
{
    public function __construct(
        protected ConvertFrozenSaleToSale $convertFrozenSaleToSale,
    ) {
    }

    public function handle(Company $company, DiningTable $table): Sale
    {
        if ($table->company_id !== $company->id) {
            throw new InvalidArgumentException('La mesa no pertenece a la empresa indicada.');
        }

        return DB::transaction(function () use ($company, $table) {
            $table = DiningTable::query()->lockForUpdate()->findOrFail($table->id);
            $openFrozenSale = $table->openFrozenSale();

            if (! $openFrozenSale) {
                throw new InvalidArgumentException('Esta mesa no tiene una comanda abierta para cobrar.');
            }

            $sale = $this->convertFrozenSaleToSale->handle($company, $openFrozenSale, [
                'sold_at' => now(),
            ]);

            $table->update(['occupancy_status' => 'free']);

            return $sale;
        });
    }
}
