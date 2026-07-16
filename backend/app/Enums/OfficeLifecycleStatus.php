<?php

namespace App\Enums;

enum OfficeLifecycleStatus: string
{
    case PendingActivation = 'PENDING_ACTIVATION';
    case Active = 'ACTIVE';

    public function isPending(): bool
    {
        return $this === self::PendingActivation;
    }

    public function isOperational(): bool
    {
        return $this === self::Active;
    }
}
