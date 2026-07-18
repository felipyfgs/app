<?php

namespace App\Enums;

/**
 * Proveniência verificável de runs/evidências/snapshots fiscais SERPRO.
 * Definida pelo driver — nunca pelo payload ou frontend.
 */
enum FiscalSourceProvenance: string
{
    case SerproTrial = 'SERPRO_TRIAL';
    case SerproReal = 'SERPRO_REAL';
    case Unverified = 'UNVERIFIED';

    public function isVerifiableCurrent(): bool
    {
        return match ($this) {
            self::SerproReal => true,
            self::SerproTrial, self::Unverified => false,
        };
    }

    public function isOfficialFiscalState(): bool
    {
        return $this === self::SerproReal;
    }
}
