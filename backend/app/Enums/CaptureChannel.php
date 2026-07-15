<?php

namespace App\Enums;

/**
 * Canais de captura DF-e (cursor NSU independente por canal).
 */
enum CaptureChannel: string
{
    case NfseAdn = 'NFSE_ADN';
    case NfeDistDfe = 'NFE_DISTDFE';
    case CteDistDfe = 'CTE_DISTDFE';
    case MdfeDistDfe = 'MDFE_DISTDFE';

    public function label(): string
    {
        return match ($this) {
            self::NfseAdn => 'NFS-e ADN',
            self::NfeDistDfe => 'NF-e DistDFe',
            self::CteDistDfe => 'CT-e DistDFe',
            self::MdfeDistDfe => 'MDF-e DistDFe',
        };
    }

    public function source(): string
    {
        return match ($this) {
            self::NfseAdn => 'ADN',
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
        };
    }

    public function featureFlagKey(): string
    {
        return match ($this) {
            self::NfseAdn => 'adn.enabled',
            self::NfeDistDfe => 'sefaz.distdfe_enabled',
            self::CteDistDfe => 'sefaz.cte_enabled',
            self::MdfeDistDfe => 'sefaz.mdfe_enabled',
        };
    }

    public function isEnabled(): bool
    {
        if ($this === self::NfseAdn) {
            return true;
        }

        return (bool) config($this->featureFlagKey(), false);
    }
}
