<?php

namespace App\Actions\Dining;

use App\Models\DiningOrderItem;
use InvalidArgumentException;

/**
 * Cocina reconoce un plato "cancelled" (ya lo vio) y lo saca del tablero.
 * Solo aplica a platos cancelados — un plato activo no se descarta, se
 * avanza o se cancela desde la comanda.
 */
class DismissDiningOrderItem
{
    public function handle(DiningOrderItem $item): void
    {
        if ($item->kitchen_status !== 'cancelled') {
            throw new InvalidArgumentException('Solo se pueden descartar platos cancelados.');
        }

        $item->delete();
    }
}
