<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Models\Company;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ModifySale
{
    public function __construct(
        protected CreatePosSale $createPosSale,
        protected CancelSale $cancelSale,
    ) {
    }

    public function handle(Company $company, Sale $originalSale, array $saleAttributes, array $paymentAttributes = []): Sale
    {
        return DB::transaction(function () use ($company, $originalSale, $saleAttributes, $paymentAttributes) {
            $locked = Sale::query()->lockForUpdate()->findOrFail($originalSale->id);

            if ($locked->company_id !== $company->id) {
                throw new InvalidArgumentException('La venta no pertenece a la empresa indicada.');
            }

            if ($locked->status !== SaleStatus::Confirmed->value) {
                throw new InvalidArgumentException('Esta venta ya fue modificada o anulada por otro proceso.');
            }

            if ($locked->sale_type !== 'pos') {
                throw new InvalidArgumentException('Solo se pueden modificar ventas de tipo POS.');
            }

            // Anular primero: libera el stock y descuenta el conteo mensual de ventas
            // confirmadas antes de crear la venta nueva, para que una correccion legitima
            // (misma cantidad o mas de un producto ya descontado) no falle por stock
            // insuficiente ni por el limite mensual del plan.
            $this->cancelSale->handle($company, $locked, 'Modificacion de venta: se reemplaza por un nuevo documento POS.');

            $newSale = $this->createPosSale->handle($company, $saleAttributes, $paymentAttributes);

            $newSale->update(['replaces_sale_id' => $locked->id]);

            return $newSale->fresh(['items', 'payments']);
        });
    }
}
