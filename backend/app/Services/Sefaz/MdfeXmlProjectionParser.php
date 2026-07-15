<?php

namespace App\Services\Sefaz;

use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use Carbon\CarbonImmutable;

/**
 * Extração tolerante de campos de catálogo a partir de procMDFe / resMDFe.
 */
final class MdfeXmlProjectionParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $xml, string $schemaFamily, ?string $establishmentCnpj = null): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        $ok = @$doc->loadXML($xml);
        libxml_use_internal_errors($prev);

        $base = [
            'access_key' => null,
            'number' => null,
            'series' => null,
            'model' => '58',
            'issuer_cnpj' => null,
            'issuer_name' => null,
            'issued_at' => null,
            'total_amount' => null,
            'status' => 'UNKNOWN',
            'official_status_code' => null,
            'is_summary' => $schemaFamily === 'resMDFe',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
        ];

        if (! $ok) {
            return $base;
        }

        $parsed = match ($schemaFamily) {
            'resMDFe' => array_merge($base, $this->parseRes($doc)),
            'procMDFe', 'MDFe' => array_merge($base, $this->parseProc($doc)),
            'procEventoMDFe' => array_merge($base, $this->parseEvento($doc)),
            default => array_merge($base, $this->parseProc($doc)),
        };

        $role = $this->resolveRole($parsed, $establishmentCnpj);
        $parsed['fiscal_role'] = $role;
        $parsed['direction'] = DocumentDirection::fromFiscalRole($role);

        return $parsed;
    }

    /** @return array<string, mixed> */
    private function parseRes(\DOMDocument $doc): array
    {
        $key = $this->first($doc, ['//*[local-name()="chMDFe"]']);

        return [
            'access_key' => $key ? strtoupper($key) : null,
            'issuer_cnpj' => $this->cnpj($doc, ['//*[local-name()="CNPJ"]']),
            'issuer_name' => $this->first($doc, ['//*[local-name()="xNome"]']),
            'issued_at' => $this->date($this->first($doc, ['//*[local-name()="dhEmi"]'])),
            'status' => 'SUMMARY',
            'is_summary' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function parseProc(\DOMDocument $doc): array
    {
        $key = $this->first($doc, [
            '//*[local-name()="infMDFe"]/@Id',
            '//*[local-name()="chMDFe"]',
        ]);
        if ($key && str_starts_with(strtoupper($key), 'MDFE')) {
            $key = substr($key, 4);
        }

        $cStat = $this->first($doc, [
            '//*[local-name()="protMDFe"]//*[local-name()="cStat"]',
            '//*[local-name()="cStat"]',
        ]);
        $status = match ($cStat) {
            '100', '150' => 'ACTIVE',
            '101', '151', '155' => 'CANCELLED',
            default => 'UNKNOWN',
        };

        return [
            'access_key' => $key ? strtoupper(preg_replace('/[^A-Z0-9]/i', '', $key) ?? $key) : null,
            'number' => $this->first($doc, ['//*[local-name()="ide"]/*[local-name()="nMDF"]']),
            'series' => $this->first($doc, ['//*[local-name()="ide"]/*[local-name()="serie"]']),
            'model' => $this->first($doc, ['//*[local-name()="ide"]/*[local-name()="mod"]']) ?? '58',
            'issuer_cnpj' => $this->cnpj($doc, [
                '//*[local-name()="emit"]//*[local-name()="CNPJ"]',
            ]),
            'issuer_name' => $this->first($doc, ['//*[local-name()="emit"]/*[local-name()="xNome"]']),
            'issued_at' => $this->date($this->first($doc, ['//*[local-name()="ide"]/*[local-name()="dhEmi"]'])),
            'status' => $status,
            'official_status_code' => $cStat,
            'is_summary' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function parseEvento(\DOMDocument $doc): array
    {
        $key = $this->first($doc, ['//*[local-name()="chMDFe"]']);
        $tp = $this->first($doc, ['//*[local-name()="tpEvento"]']);

        return [
            'access_key' => $key ? strtoupper($key) : null,
            'is_summary' => false,
            'status' => $tp === '110111' ? 'CANCELLED' : 'EVENT',
            'official_status_code' => $tp,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function resolveRole(array $parsed, ?string $establishmentCnpj): FiscalRole
    {
        if ($establishmentCnpj && strtoupper($establishmentCnpj) === ($parsed['issuer_cnpj'] ?? null)) {
            return FiscalRole::Issuer;
        }

        return FiscalRole::Taker;
    }

    private function first(\DOMDocument $doc, array $xpaths): ?string
    {
        $xp = new \DOMXPath($doc);
        foreach ($xpaths as $q) {
            if (str_contains($q, '/@')) {
                [$path, $attr] = explode('/@', $q, 2);
                $nodes = $xp->query($path);
                if ($nodes && $nodes->length > 0) {
                    $el = $nodes->item(0);
                    if ($el instanceof \DOMElement && $el->hasAttribute($attr)) {
                        $v = trim($el->getAttribute($attr));
                        if ($v !== '') {
                            return $v;
                        }
                    }
                }

                continue;
            }
            $nodes = $xp->query($q);
            if ($nodes && $nodes->length > 0) {
                $v = trim($nodes->item(0)?->textContent ?? '');
                if ($v !== '') {
                    return $v;
                }
            }
        }

        return null;
    }

    private function cnpj(\DOMDocument $doc, array $xpaths): ?string
    {
        $raw = $this->first($doc, $xpaths);
        if ($raw === null) {
            return null;
        }
        $c = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $raw) ?? $raw);

        return strlen($c) >= 11 ? $c : null;
    }

    private function date(?string $raw): ?CarbonImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
