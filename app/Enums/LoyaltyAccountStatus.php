<?php

namespace App\Enums;

enum LoyaltyAccountStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Closed = 'closed';
}
