<?php

namespace App\Enums;

/**
 * Papéis da plataforma (globais). Separados de {@see OfficeRole}.
 * PLATFORM_ADMIN NÃO concede acesso fiscal implícito a tenants.
 */
enum PlatformRole: string
{
    case PlatformAdmin = 'PLATFORM_ADMIN';
}
