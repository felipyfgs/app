<?php

namespace App\Enums;

enum FiscalRole: string
{
    case Issuer = 'ISSUER';
    case Taker = 'TAKER';
    case Intermediary = 'INTERMEDIARY';
}
