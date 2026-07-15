<?php

namespace App\Enums;

enum SerproEnvironment: string
{
    case Trial = 'TRIAL';
    case Homologation = 'HOMOLOGATION';
    case Production = 'PRODUCTION';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial / simulado',
            self::Homologation => 'Homologação',
            self::Production => 'Produção',
        };
    }

    public function isSimulatedDefault(): bool
    {
        return $this === self::Trial;
    }
}
