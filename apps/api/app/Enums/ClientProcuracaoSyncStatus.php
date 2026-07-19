<?php

namespace App\Enums;

/**
 * Estado sincronizado da procuração do cliente (evidência oficial e-CAC).
 * Sem override manual — apenas sync oficial.
 */
enum ClientProcuracaoSyncStatus: string
{
    case Authorized = 'authorized';
    case Missing = 'missing';
    case Expired = 'expired';
    case Unverified = 'unverified';

    public function isUsable(): bool
    {
        return $this === self::Authorized;
    }
}
