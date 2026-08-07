<?php

namespace App\Enums;

enum FrozenSaleStatus: string
{
    case Open = 'open';
    case Expired = 'expired';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
}
