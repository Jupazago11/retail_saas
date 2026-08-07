<?php

namespace App\Enums;

enum PromotionTargetType: string
{
    case Product = 'product';
    case Category = 'category';
    case Variant = 'variant';
}
