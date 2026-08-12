<?php

namespace App\Enums;

enum EquipmentRentalStatus: string
{
    case Requested = 'requested';
    case Active = 'active';
    case PendingReturn = 'pending_return';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitado',
            self::Active => 'Alquilado',
            self::PendingReturn => 'Pendiente de devolucion',
            self::Returned => 'Devuelto',
        };
    }
}
