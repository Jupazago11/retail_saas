<?php

namespace App\Enums;

enum InventoryAdjustmentType: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';
}
