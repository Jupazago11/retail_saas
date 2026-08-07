<?php

namespace App\Enums;

enum PromotionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
