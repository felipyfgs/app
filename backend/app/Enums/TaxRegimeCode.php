<?php

namespace App\Enums;

/**
 * Regime tributário canônico para regras de aplicabilidade e projeções SN/MEI.
 * Valores livres em clients.tax_regime são normalizados via normalize().
 * Nunca misturar competências de regimes distintos.
 */
enum TaxRegimeCode: string
{
    case SimplesNacional = 'SIMPLES_NACIONAL';
    case Mei = 'MEI';
    case LucroPresumido = 'LUCRO_PRESUMIDO';
    case LucroReal = 'LUCRO_REAL';
    case Outro = 'OUTRO';
    case Unknown = 'UNKNOWN';

    /**
     * Normaliza string livre (cadastro do cliente) para código canônico.
     */
    public static function normalize(?string $raw): self
    {
        if ($raw === null || trim($raw) === '') {
            return self::Unknown;
        }

        $key = strtoupper(trim($raw));
        $key = str_replace([' ', '-', '.'], '_', $key);

        return match ($key) {
            'SIMPLES_NACIONAL', 'SIMPLES', 'SN', 'SIMPLES_NAC' => self::SimplesNacional,
            'MEI', 'SIMEI', 'MICROEMPREENDEDOR_INDIVIDUAL' => self::Mei,
            'LUCRO_PRESUMIDO', 'PRESUMIDO', 'LP' => self::LucroPresumido,
            'LUCRO_REAL', 'REAL', 'LR' => self::LucroReal,
            'OUTRO', 'OTHER' => self::Outro,
            default => self::tryFrom($key) ?? self::Unknown,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SimplesNacional => 'Simples Nacional',
            self::Mei => 'MEI / SIMEI',
            self::LucroPresumido => 'Lucro Presumido',
            self::LucroReal => 'Lucro Real',
            self::Outro => 'Outro regime',
            self::Unknown => 'Desconhecido',
        };
    }

    public function fiscalCategoryCode(): ?string
    {
        return match ($this) {
            self::SimplesNacional => 'SIMPLES_NACIONAL',
            self::Mei => 'MEI',
            default => null,
        };
    }

    public function isSimplesFamily(): bool
    {
        return $this === self::SimplesNacional;
    }

    public function isMeiFamily(): bool
    {
        return $this === self::Mei;
    }

    /** SN e MEI não compartilham projeções de obrigação. */
    public function compatibleWith(self $other): bool
    {
        if ($this === self::Unknown || $other === self::Unknown) {
            return false;
        }

        return $this === $other;
    }
}
