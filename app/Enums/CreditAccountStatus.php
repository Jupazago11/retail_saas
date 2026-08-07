<?php

namespace App\Enums;

enum CreditAccountStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Closed = 'closed';
}
