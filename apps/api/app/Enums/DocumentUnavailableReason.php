<?php

namespace App\Enums;

/**
 * Motivo público de indisponibilidade do descritor de documento tenant-scoped.
 */
enum DocumentUnavailableReason: string
{
    case StructuredOnly = 'STRUCTURED_ONLY';
    case Processing = 'PROCESSING';
    case NotSupported = 'NOT_SUPPORTED';
    case NotProduction = 'NOT_PRODUCTION';
    case NotCollected = 'NOT_COLLECTED';

    public function label(): string
    {
        return match ($this) {
            self::StructuredOnly => 'Somente dados estruturados',
            self::Processing => 'Processando',
            self::NotSupported => 'Documento não suportado',
            self::NotProduction => 'Operação não produtiva',
            self::NotCollected => 'Documento ainda não coletado',
        };
    }
}
