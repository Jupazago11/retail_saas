<?php

namespace App\Enums;

enum PayableMovementType: string
{
    case PurchaseCharge = 'purchase_charge';
    case Payment = 'payment';
    case PurchaseReturnAdjustment = 'purchase_return_adjustment';
    case SupplierCreditGenerated = 'supplier_credit_generated';
    case SupplierCreditApplied = 'supplier_credit_applied';
}
