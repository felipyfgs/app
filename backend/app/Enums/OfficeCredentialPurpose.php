<?php

namespace App\Enums;

enum OfficeCredentialPurpose: string
{
    case NfeAutXmlDistDfe = 'NFE_AUTXML_DISTDFE';

    public function label(): string
    {
        return match ($this) {
            self::NfeAutXmlDistDfe => 'DistDFe autXML (escritório)',
        };
    }
}
