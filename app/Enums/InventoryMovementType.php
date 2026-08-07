<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case OpeningBalance = 'opening_balance';
    case PurchaseIn = 'purchase_in';
    case PurchaseReturnOut = 'purchase_return_out';
    case SaleOut = 'sale_out';
    case SaleReturnIn = 'sale_return_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
}
