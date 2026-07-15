<?php

namespace App\Enums;

/**
 * Tipos DF-e mais comuns no catálogo Documentos.
 * Capture disponível hoje: apenas NFS-e via ADN.
 */
enum DocumentKind: string
{
    case Nfse = 'NFSE';
    case Nfe = 'NFE';
    case Nfce = 'NFCE';
    case Cte = 'CTE';
    case Mdfe = 'MDFE';

    public function label(): string
    {
        return match ($this) {
            self::Nfse => 'NFS-e',
            self::Nfe => 'NF-e',
            self::Nfce => 'NFC-e',
            self::Cte => 'CT-e',
            self::Mdfe => 'MDF-e',
        };
    }

    /** Modelo SEFAZ quando aplicável (null para NFS-e nacional). */
    public function sefazModel(): ?string
    {
        return match ($this) {
            self::Nfe => '55',
            self::Nfce => '65',
            self::Cte => '57',
            self::Mdfe => '58',
            default => null,
        };
    }

    /**
     * Captura habilitada na instância (feature flag + implementação).
     * NFS-e: sempre via ADN. Demais: config sefaz.*_enabled.
     */
    public function captureAvailable(): bool
    {
        return match ($this) {
            self::Nfse => true,
            self::Nfe => (bool) config('sefaz.distdfe_enabled', false),
            self::Cte => (bool) config('sefaz.cte_enabled', false),
            self::Mdfe => (bool) config('sefaz.mdfe_enabled', false),
            self::Nfce => (bool) config('sefaz.nfce_enabled', false),
        };
    }

    public function defaultSource(): ?string
    {
        return match ($this) {
            self::Nfse => 'ADN',
            default => 'SEFAZ',
        };
    }

    /**
     * @return list<self>
     */
    public static function catalogFilterOptions(): array
    {
        return [
            self::Nfse,
            self::Nfe,
            self::Nfce,
            self::Cte,
            self::Mdfe,
        ];
    }

    public static function tryFromRequest(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtoupper(str_replace(['-', ' '], ['_', ''], trim($value)));
        $aliases = [
            'NFS_E' => 'NFSE',
            'NF_E' => 'NFE',
            'NFC_E' => 'NFCE',
            'CT_E' => 'CTE',
            'MDF_E' => 'MDFE',
        ];
        $code = $aliases[$normalized] ?? $normalized;

        return self::tryFrom($code);
    }

    /**
     * Extrai lista de kinds do request (kind=NFSE ou kind[]=NFE&kind[]=CTE).
     *
     * @return list<self>
     */
    public static function listFromRequest(\Illuminate\Http\Request $request): array
    {
        $raw = $request->input('kind');
        if ($raw === null || $raw === '' || $raw === 'all') {
            return [];
        }

        $values = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($values as $v) {
            $kind = self::tryFromRequest(is_string($v) ? $v : null);
            if ($kind !== null) {
                $out[$kind->value] = $kind;
            }
        }

        return array_values($out);
    }

    /**
     * True se o filtro inclui NFS-e (ou não há filtro de kind).
     *
     * @param  list<self>  $kinds
     */
    public static function includesNfse(array $kinds): bool
    {
        return self::includes($kinds, self::Nfse);
    }

    /**
     * @param  list<self>  $kinds
     */
    public static function includes(array $kinds, self $target): bool
    {
        if ($kinds === []) {
            return true;
        }

        foreach ($kinds as $kind) {
            if ($kind === $target) {
                return true;
            }
        }

        return false;
    }
}
