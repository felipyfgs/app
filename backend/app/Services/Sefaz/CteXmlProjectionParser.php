<?php

namespace App\Services\Sefaz;

use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use Carbon\CarbonImmutable;

/**
 * Extração tolerante de campos de catálogo a partir de procCTe / resCTe / evento.
 *
 * @return array<string, mixed>
 */
final class CteXmlProjectionParser
{
    /**
     * @return array{
     *   access_key: ?string,
     *   number: ?string,
     *   series: ?string,
     *   model: string,
     *   issuer_cnpj: ?string,
     *   issuer_name: ?string,
     *   taker_cnpj: ?string,
     *   taker_name: ?string,
     *   sender_cnpj: ?string,
     *   recipient_cnpj: ?string,
     *   issued_at: ?CarbonImmutable,
     *   total_amount: ?string,
     *   status: string,
     *   official_status_code: ?string,
     *   is_summary: bool,
     *   fiscal_role: ?FiscalRole,
     *   direction: DocumentDirection,
     * }
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
            'model' => '57',
            'issuer_cnpj' => null,
            'issuer_name' => null,
            'taker_cnpj' => null,
            'taker_name' => null,
            'sender_cnpj' => null,
            'recipient_cnpj' => null,
            'issued_at' => null,
            'total_amount' => null,
            'status' => 'UNKNOWN',
            'official_status_code' => null,
            'is_summary' => $schemaFamily === 'resCTe',
            'fiscal_role' => null,
            'direction' => DocumentDirection::In,
        ];

        if (! $ok) {
            return $base;
        }

        $parsed = match ($schemaFamily) {
            'resCTe' => array_merge($base, $this->parseResCte($doc)),
            'procCTe', 'CTe' => array_merge($base, $this->parseProcCte($doc)),
            'procEventoCTe', 'retEventoCTe' => array_merge($base, $this->parseEvento($doc)),
            default => array_merge($base, $this->parseProcCte($doc)),
        };

        $role = $this->resolveRole($parsed, $establishmentCnpj);
        $parsed['fiscal_role'] = $role;
        $parsed['direction'] = DocumentDirection::fromFiscalRole($role);

        return $parsed;
    }

    /** @return array<string, mixed> */
    private function parseResCte(\DOMDocument $doc): array
    {
        $key = $this->first($doc, ['//*[local-name()="chCTe"]']);

        return [
            'access_key' => $key ? strtoupper($key) : null,
            'issuer_cnpj' => $this->cnpj($doc, ['//*[local-name()="CNPJ"]', '//*[local-name()="CPF"]']),
            'issuer_name' => $this->first($doc, ['//*[local-name()="xNome"]']),
            'issued_at' => $this->date($this->first($doc, ['//*[local-name()="dhEmi"]', '//*[local-name()="dEmi"]'])),
            'total_amount' => $this->first($doc, ['//*[local-name()="vTPrest"]', '//*[local-name()="vCTe"]']),
            'status' => 'SUMMARY',
            'is_summary' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function parseProcCte(\DOMDocument $doc): array
    {
        $key = $this->first($doc, [
            '//*[local-name()="infCte"]/@Id',
            '//*[local-name()="chCTe"]',
        ]);
        if ($key && str_starts_with(strtoupper($key), 'CTE')) {
            $key = substr($key, 3);
        }

        $cStat = $this->first($doc, [
            '//*[local-name()="protCTe"]//*[local-name()="cStat"]',
            '//*[local-name()="cStat"]',
        ]);
        $status = match ($cStat) {
            '100', '150' => 'ACTIVE',
            '101', '151', '155' => 'CANCELLED',
            '110', '301', '302' => 'DENIED',
            default => 'UNKNOWN',
        };

        return [
            'access_key' => $key ? strtoupper(preg_replace('/[^A-Z0-9]/i', '', $key) ?? $key) : null,
            'number' => $this->first($doc, ['//*[local-name()="ide"]/*[local-name()="nCT"]']),
            'series' => $this->first($doc, ['//*[local-name()="ide"]/*[local-name()="serie"]']),
            'model' => $this->first($doc, ['//*[local-name()="ide"]/*[local-name()="mod"]']) ?? '57',
            'issuer_cnpj' => $this->cnpj($doc, [
                '//*[local-name()="emit"]//*[local-name()="CNPJ"]',
                '//*[local-name()="emit"]//*[local-name()="CPF"]',
            ]),
            'issuer_name' => $this->first($doc, ['//*[local-name()="emit"]/*[local-name()="xNome"]']),
            'taker_cnpj' => $this->cnpj($doc, [
                '//*[local-name()="toma3"]//*[local-name()="CNPJ"]',
                '//*[local-name()="toma4"]//*[local-name()="CNPJ"]',
                '//*[local-name()="toma"]//*[local-name()="CNPJ"]',
            ]),
            'taker_name' => $this->first($doc, [
                '//*[local-name()="toma4"]/*[local-name()="xNome"]',
                '//*[local-name()="toma"]/*[local-name()="xNome"]',
            ]),
            'sender_cnpj' => $this->cnpj($doc, [
                '//*[local-name()="rem"]//*[local-name()="CNPJ"]',
                '//*[local-name()="rem"]//*[local-name()="CPF"]',
            ]),
            'recipient_cnpj' => $this->cnpj($doc, [
                '//*[local-name()="dest"]//*[local-name()="CNPJ"]',
                '//*[local-name()="dest"]//*[local-name()="CPF"]',
            ]),
            'issued_at' => $this->date($this->first($doc, ['//*[local-name()="ide"]/*[local-name()="dhEmi"]'])),
            'total_amount' => $this->first($doc, [
                '//*[local-name()="vPrest"]/*[local-name()="vTPrest"]',
                '//*[local-name()="vTPrest"]',
            ]),
            'status' => $status,
            'official_status_code' => $cStat,
            'is_summary' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function parseEvento(\DOMDocument $doc): array
    {
        $key = $this->first($doc, ['//*[local-name()="chCTe"]']);
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
    private function resolveRole(array $parsed, ?string $establishmentCnpj): ?FiscalRole
    {
        if ($establishmentCnpj === null || $establishmentCnpj === '') {
            return FiscalRole::Taker; // DistDFe de interesse → entrada padrão
        }
        $cnpj = strtoupper($establishmentCnpj);
        if (($parsed['issuer_cnpj'] ?? null) === $cnpj) {
            return FiscalRole::Issuer;
        }
        if (($parsed['taker_cnpj'] ?? null) === $cnpj) {
            return FiscalRole::Taker;
        }
        if (in_array($cnpj, array_filter([
            $parsed['sender_cnpj'] ?? null,
            $parsed['recipient_cnpj'] ?? null,
        ]), true)) {
            return FiscalRole::Taker;
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
