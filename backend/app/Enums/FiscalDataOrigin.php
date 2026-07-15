<?php

namespace App\Enums;

/**
 * Proveniência sanitizada dos dados fiscais expostos em overview/carteira.
 * Nunca expõe seeder interno, vault path ou credenciais.
 */
enum FiscalDataOrigin: string
{
    case Demo = 'DEMO';
    case Simulated = 'SIMULATED';
    case Live = 'LIVE';

    public function label(): string
    {
        return match ($this) {
            self::Demo => 'Dados demonstrativos',
            self::Simulated => 'Dados simulados',
            self::Live => 'Fonte produtiva',
        };
    }

    public function isSynthetic(): bool
    {
        return $this !== self::Live;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $o) => $o->value, self::cases());
    }

    /**
     * Envelope sanitizado para APIs de overview/carteira/detalhe.
     *
     * @return array{origin: string, label: string, synthetic: bool, banner: string|null}
     */
    public function toPublicArray(): array
    {
        return [
            'origin' => $this->value,
            'label' => $this->label(),
            'synthetic' => $this->isSynthetic(),
            'banner' => $this->isSynthetic()
                ? 'Dados demonstrativos — sem validade fiscal'
                : null,
        ];
    }
}
