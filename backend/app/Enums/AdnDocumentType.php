<?php

namespace App\Enums;

enum AdnDocumentType: string
{
    case Nfse = 'NFSE';
    case Event = 'EVENTO';
    case Unknown = 'UNKNOWN';
}
