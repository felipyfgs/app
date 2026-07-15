<?php

namespace App\Enums;

/**
 * Canais de captura DF-e reconhecidos pelo domínio.
 * MDF-e permanece apenas para hidratar valores legados e nunca é operacional.
 */
enum CaptureChannel: string
{
    case NfseAdn = 'NFSE_ADN';
    case NfeDistDfe = 'NFE_DISTDFE';
    case CteDistDfe = 'CTE_DISTDFE';
    case MdfeDistDfe = 'MDFE_DISTDFE';
    /** Captura de saídas MA (nNF — nunca last_nsu). */
    case MaOutbound = 'MA_OUTBOUND';

    public function label(): string
    {
        return match ($this) {
            self::NfseAdn => 'NFS-e ADN',
            self::NfeDistDfe => 'NF-e DistDFe',
            self::CteDistDfe => 'CT-e DistDFe',
            self::MdfeDistDfe => 'MDF-e DistDFe',
            self::MaOutbound => 'Saídas MA (NF-e/NFC-e)',
        };
    }

    public function source(): string
    {
        return match ($this) {
            self::NfseAdn => 'ADN',
            self::MaOutbound => 'SEFAZ_MA',
            default => 'SEFAZ',
        };
    }

    public function documentKind(): ?DocumentKind
    {
        return match ($this) {
            self::NfseAdn => DocumentKind::Nfse,
            self::NfeDistDfe => DocumentKind::Nfe,
            self::CteDistDfe => DocumentKind::Cte,
            self::MdfeDistDfe => DocumentKind::Mdfe,
            self::MaOutbound => null, // 55 e 65 no mesmo canal
        };
    }

    public function featureFlagKey(): string
    {
        return match ($this) {
            self::NfseAdn => 'adn.enabled',
            self::NfeDistDfe => 'sefaz.distdfe_enabled',
            self::CteDistDfe => 'sefaz.cte_enabled',
            self::MdfeDistDfe => 'sefaz.mdfe_enabled',
            self::MaOutbound => 'sefaz.ma_outbound.enabled',
        };
    }

    public function isEnabled(): bool
    {
        if ($this === self::MdfeDistDfe) {
            return false;
        }

        if ($this === self::NfseAdn) {
            return true;
        }

        return (bool) config($this->featureFlagKey(), false);
    }

    /**
     * Canais baseados em NSU (não misturar com posição nNF).
     */
    public function usesNsuCursor(): bool
    {
        return $this !== self::MaOutbound;
    }

    /**
     * @return list<self>
     */
    public static function operationalCases(): array
    {
        return [
            self::NfseAdn,
            self::NfeDistDfe,
            self::CteDistDfe,
            self::MaOutbound,
        ];
    }
}
