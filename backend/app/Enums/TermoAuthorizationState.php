<?php

namespace App\Enums;

/**
 * Estado do Termo / representação do Autor do Pedido.
 * LOCAL_VALIDATED ≠ aceite SERPRO.
 */
enum TermoAuthorizationState: string
{
    case LocalValidated = 'LOCAL_VALIDATED';
    case SerproAccepted = 'SERPRO_ACCEPTED';
    case Simulated = 'SIMULATED';
    case Rejected = 'REJECTED';
    case Pending = 'PENDING';
    case Expired = 'EXPIRED';
}
