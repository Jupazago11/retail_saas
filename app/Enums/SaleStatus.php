<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';
}
