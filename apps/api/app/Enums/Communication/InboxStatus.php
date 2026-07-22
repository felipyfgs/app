<?php

namespace App\Enums\Communication;

enum InboxStatus: string
{
    case Disabled = 'DISABLED';
    case Provisioned = 'PROVISIONED';
    case Pairing = 'PAIRING';
    case Connected = 'CONNECTED';
    case Degraded = 'DEGRADED';
    case Revoked = 'REVOKED';

    public function canTransport(): bool
    {
        return $this === self::Connected;
    }
}
