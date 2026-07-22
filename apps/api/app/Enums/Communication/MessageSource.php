<?php

namespace App\Enums\Communication;

enum MessageSource: string
{
    case Human = 'HUMAN';
    case FiscalAutomation = 'FISCAL_AUTOMATION';
    case Gateway = 'GATEWAY';
}
