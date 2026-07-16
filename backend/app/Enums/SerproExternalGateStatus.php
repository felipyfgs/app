<?php

namespace App\Enums;

enum SerproExternalGateStatus: string
{
    case Open = 'OPEN';
    case Submitted = 'SUBMITTED';
    case Answered = 'ANSWERED';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Waived = 'WAIVED';

    public function blocksProduction(): bool
    {
        return match ($this) {
            self::Accepted, self::Waived => false,
            default => true,
        };
    }
}
