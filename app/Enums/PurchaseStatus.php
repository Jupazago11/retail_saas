<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Confirmed = 'confirmed';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
