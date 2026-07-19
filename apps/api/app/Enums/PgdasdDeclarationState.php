<?php

namespace App\Enums;

/**
 * Estado operacional da declaração PGDAS-D para o PA esperado (fail-closed).
 */
enum PgdasdDeclarationState: string
{
    case Current = 'CURRENT';
    case DueWithinDeadline = 'DUE_WITHIN_DEADLINE';
    case OverdueNotFound = 'OVERDUE_NOT_FOUND';
    case Unverified = 'UNVERIFIED';
}
