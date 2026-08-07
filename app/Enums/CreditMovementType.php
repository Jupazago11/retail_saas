<?php

namespace App\Enums;

enum CreditMovementType: string
{
    case SaleCharge = 'sale_charge';
    case Payment = 'payment';
    case SaleReturnAdjustment = 'sale_return_adjustment';
    case SaleCancellationAdjustment = 'sale_cancellation_adjustment';
}
