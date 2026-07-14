<?php

namespace App\Services\Adn;

use App\Enums\FiscalRole;
use Carbon\CarbonImmutable;

final class NfseXmlParser
{
    /**
     * @return array{
     *   access_key: ?string,
     *   issuer_cnpj: ?string,
     *   taker_cnpj: ?string,
     *   intermediary_cnpj: ?string,
     *   competence: ?string,
     *   issued_at: ?CarbonImmutable,
     *   service_amount: ?string,
     *   status: string,
     *   parse_status: string,
     *   parse_alert: ?string,
     *   fiscal_role_for: callable(string): ?FiscalRole
     * }
     */
    public function parseNote(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        $ok = @$doc->loadXML($xml);
        libxml_use_internal_errors($prev);

        if (! $ok) {
            return $this->failed('XML malformado.');
        }

        $accessKey = $this->first($doc, ['//*[local-name()="chNFSe"]', '//*[local-name()="chaveAcesso"]']);
        $issuer = $this->cnpj($doc, ['//*[local-name()="emit"]//*[local-name()="CNPJ"]', '//*[local-name()="prestador"]//*[local-name()="CNPJ"]']);
        $taker = $this->cnpj($doc, ['//*[local-name()="toma"]//*[local-name()="CNPJ"]', '//*[local-name()="tomador"]//*[local-name()="CNPJ"]']);
        $intermediary = $this->cnpj($doc, ['//*[local-name()="intermediario"]//*[local-name()="CNPJ"]']);
        $competence = $this->first($doc, ['//*[local-name()="dCompet"]', '//*[local-name()="competencia"]']);
        $issuedRaw = $this->first($doc, ['//*[local-name()="dhEmi"]', '//*[local-name()="dataEmissao"]']);
        $amount = $this->first($doc, ['//*[local-name()="vServ"]', '//*[local-name()="valorServicos"]']);

        $parseStatus = 'OK';
        $alert = null;
        if ($accessKey === null) {
            $parseStatus = 'REVIEW';
            $alert = 'Chave de acesso não encontrada; XML preservado.';
        }

        return [
            'access_key' => $accessKey,
            'issuer_cnpj' => $issuer,
            'taker_cnpj' => $taker,
            'intermediary_cnpj' => $intermediary,
            'competence' => $this->normalizeCompetence($competence),
            'issued_at' => $issuedRaw ? CarbonImmutable::parse($issuedRaw) : null,
            'service_amount' => $amount,
            'status' => 'ACTIVE',
            'parse_status' => $parseStatus,
            'parse_alert' => $alert,
            'fiscal_role_for' => function (string $establishmentCnpj) use ($issuer, $taker, $intermediary): ?FiscalRole {
                $c = strtoupper($establishmentCnpj);
                if ($issuer === $c) {
                    return FiscalRole::Issuer;
                }
                if ($taker === $c) {
                    return FiscalRole::Taker;
                }
                if ($intermediary === $c) {
                    return FiscalRole::Intermediary;
                }

                return null;
            },
        ];
    }

    /**
     * @return array{access_key: ?string, event_type: ?string, event_at: ?CarbonImmutable, status: ?string, parse_status: string, parse_alert: ?string}
     */
    public function parseEvent(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        $ok = @$doc->loadXML($xml);
        libxml_use_internal_errors($prev);

        if (! $ok) {
            return [
                'access_key' => null,
                'event_type' => null,
                'event_at' => null,
                'status' => null,
                'parse_status' => 'FAILED',
                'parse_alert' => 'XML de evento malformado.',
            ];
        }

        return [
            'access_key' => $this->first($doc, ['//*[local-name()="chNFSe"]', '//*[local-name()="chaveAcesso"]']),
            'event_type' => $this->first($doc, ['//*[local-name()="tpEvento"]', '//*[local-name()="tipoEvento"]']),
            'event_at' => ($raw = $this->first($doc, ['//*[local-name()="dhEvento"]'])) ? CarbonImmutable::parse($raw) : null,
            'status' => $this->first($doc, ['//*[local-name()="cStat"]']),
            'parse_status' => 'OK',
            'parse_alert' => null,
        ];
    }

    /**
     * @param  list<string>  $expressions
     */
    private function first(\DOMDocument $doc, array $expressions): ?string
    {
        $xpath = new \DOMXPath($doc);
        foreach ($expressions as $expr) {
            $nodes = $xpath->query($expr);
            if ($nodes && $nodes->length > 0) {
                $value = trim($nodes->item(0)?->textContent ?? '');
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $expressions
     */
    private function cnpj(\DOMDocument $doc, array $expressions): ?string
    {
        $value = $this->first($doc, $expressions);

        return $value ? strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $value) ?? '') : null;
    }

    private function normalizeCompetence(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})/', $value, $m)) {
            return $m[1].'-'.$m[2];
        }
        if (preg_match('/^(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[2].'-'.$m[1];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function failed(string $alert): array
    {
        return [
            'access_key' => null,
            'issuer_cnpj' => null,
            'taker_cnpj' => null,
            'intermediary_cnpj' => null,
            'competence' => null,
            'issued_at' => null,
            'service_amount' => null,
            'status' => 'UNKNOWN',
            'parse_status' => 'FAILED',
            'parse_alert' => $alert,
            'fiscal_role_for' => fn () => null,
        ];
    }
}
