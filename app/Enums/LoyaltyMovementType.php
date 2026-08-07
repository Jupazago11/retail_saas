<?php

namespace App\Enums;

enum LoyaltyMovementType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case ManualCredit = 'manual_credit';
    case ManualDebit = 'manual_debit';
    case Expiration = 'expiration';
    case SaleReturnReversal = 'sale_return_reversal';
    case SaleCancellationReversal = 'sale_cancellation_reversal';
    case SaleReturnRedemptionRestore = 'sale_return_redemption_restore';
    case SaleCancellationRedemptionRestore = 'sale_cancellation_redemption_restore';
}
