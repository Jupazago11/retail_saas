<?php

namespace App\Actions\Dining;

use App\Models\DiningOrderItem;
use InvalidArgumentException;

class AdvanceDiningOrderItemStatus
{
    /**
     * pending -> preparing -> ready -> served, con una pausa opcional
     * (on_hold) desde "preparing" que vuelve a "preparing" al reanudar.
     * "cancelled" no tiene salida aqui — se descarta con
     * DismissDiningOrderItem, no se "avanza".
     */
    protected const TRANSITIONS = [
        'pending' => ['preparing'],
        'preparing' => ['on_hold', 'ready'],
        'on_hold' => ['preparing'],
        'ready' => ['served'],
    ];

    public function handle(DiningOrderItem $item, string $targetStatus): DiningOrderItem
    {
        $allowed = self::TRANSITIONS[$item->kitchen_status] ?? [];

        if (! in_array($targetStatus, $allowed, true)) {
            throw new InvalidArgumentException('Esa transicion de estado no esta permitida.');
        }

        $item->update(['kitchen_status' => $targetStatus]);

        return $item->fresh();
    }
}
