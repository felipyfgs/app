<?php

namespace App\Services\Adn;

use App\Contracts\AdnContributorClient;
use App\Domain\Adn\DistributionDocumentDto;
use App\Domain\Adn\DistributionPageDto;
use App\Domain\Adn\EventsPageDto;
use App\Enums\AdnDocumentType;
use App\Exceptions\Adn\AdnInvalidResponseException;
use App\Exceptions\Adn\AdnPermanentException;
use App\Exceptions\Adn\AdnRetryableException;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class HttpAdnContributorClient implements AdnContributorClient
{
    public function __construct(
        private readonly CurlMtlsTransport $transport,
        private readonly string $baseUrl,
    ) {}

    public function distribution(array $certificate, string $cnpjConsulta, int $lastNsu, bool $lote = true): DistributionPageDto
    {
        $nsu = max(0, $lastNsu);
        $query = http_build_query([
            'cnpjConsulta' => $cnpjConsulta,
            'lote' => $lote ? 'true' : 'false',
        ]);
        $url = rtrim($this->baseUrl, '/').'/DFe/'.$nsu.($query ? '?'.$query : '');

        $response = $this->transport->get($url, $certificate);
        $this->assertHttpOk($response['status'], $response['body']);

        return $this->parseDistribution($response['body'], $nsu);
    }

    public function events(array $certificate, string $accessKey): EventsPageDto
    {
        $url = rtrim($this->baseUrl, '/').'/NFSe/'.rawurlencode($accessKey).'/Eventos';
        $response = $this->transport->get($url, $certificate);
        $this->assertHttpOk($response['status'], $response['body']);

        $document = $this->parseXml($response['body']);
        $docs = $this->extractDocuments($document, false);

        return new EventsPageDto(
            accessKey: $accessKey,
            events: $docs,
            rawXml: $response['body'],
        );
    }

    private function assertHttpOk(int $status, string $body): void
    {
        unset($body);

        if ($status === 429) {
            throw new AdnRetryableException('ADN limitou temporariamente as requisições.', $status);
        }
        if ($status >= 500) {
            throw new AdnRetryableException('ADN apresentou indisponibilidade temporária.', $status);
        }
        if ($status < 200 || $status >= 300) {
            throw new AdnPermanentException('ADN rejeitou permanentemente a requisição.', $status);
        }
    }

    private function parseDistribution(string $xml, int $requestedNsu): DistributionPageDto
    {
        $document = $this->parseXml($xml);
        $xpath = new DOMXPath($document);
        $status = $this->requiredText($xpath, '//*[local-name()="cStat"]');
        $maxNsu = $this->requiredNsu($xpath, '//*[local-name()="maxNSU"]');
        $ultimo = $this->requiredNsu($xpath, '//*[local-name()="ultNSU"]');
        $docs = $this->extractDocuments($document, true);

        if (! in_array($status, ['137', '138'], true)
            || $ultimo < $requestedNsu
            || $maxNsu < $ultimo) {
            throw new AdnInvalidResponseException;
        }

        if ($status === '137') {
            if ($docs !== []) {
                throw new AdnInvalidResponseException;
            }

            return new DistributionPageDto(
                status: $status,
                maxNsu: $maxNsu,
                ultimoNsu: $requestedNsu,
                documents: [],
                hasMore: false,
                rawXml: $xml,
            );
        }

        if ($docs === []) {
            throw new AdnInvalidResponseException;
        }

        $documentNsus = array_map(
            fn (DistributionDocumentDto $item): int => $item->nsu,
            $docs,
        );

        if (count($documentNsus) !== count(array_unique($documentNsus))
            || min($documentNsus) <= $requestedNsu
            || max($documentNsus) !== $ultimo) {
            throw new AdnInvalidResponseException;
        }

        $hasMore = $ultimo < $maxNsu;

        return new DistributionPageDto(
            status: $status,
            maxNsu: $maxNsu,
            ultimoNsu: $ultimo,
            documents: $docs,
            hasMore: $hasMore,
            rawXml: $xml,
        );
    }

    /**
     * @return list<DistributionDocumentDto>
     */
    private function extractDocuments(DOMDocument $document, bool $requireNsu): array
    {
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[local-name()="docZip"]');

        if ($nodes === false) {
            throw new AdnInvalidResponseException;
        }

        if ($nodes->length === 0) {
            return [];
        }

        $documents = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                throw new AdnInvalidResponseException;
            }

            $rawNsu = $node->getAttribute('NSU') ?: $node->getAttribute('nsu');
            $nsu = $rawNsu === '' && ! $requireNsu ? 0 : $this->parseNsu($rawNsu);
            $schema = $node->getAttribute('schema') ?: $node->getAttribute('Schema');
            $content = trim($node->textContent ?? '');

            if ($content === '' || $schema === '') {
                throw new AdnInvalidResponseException;
            }

            $schemaLower = strtolower($schema);
            $type = str_contains($schemaLower, 'evento')
                ? AdnDocumentType::Event
                : (str_contains($schemaLower, 'nfse') ? AdnDocumentType::Nfse : AdnDocumentType::Unknown);

            $documents[] = new DistributionDocumentDto(
                nsu: $nsu,
                type: $type,
                schema: $schema,
                contentBase64: $content,
            );
        }

        return $documents;
    }

    private function parseXml(string $xml): DOMDocument
    {
        $prev = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $xml !== '' && @$document->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $loaded) {
            throw new AdnInvalidResponseException;
        }

        return $document;
    }

    private function requiredText(DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);

        if ($nodes === false || $nodes->length !== 1) {
            throw new AdnInvalidResponseException;
        }

        $value = trim($nodes->item(0)?->textContent ?? '');
        if ($value === '') {
            throw new AdnInvalidResponseException;
        }

        return $value;
    }

    private function requiredNsu(DOMXPath $xpath, string $expression): int
    {
        return $this->parseNsu($this->requiredText($xpath, $expression));
    }

    private function parseNsu(string $value): int
    {
        if (! preg_match('/^(0|[1-9][0-9]{0,18})$/', $value)) {
            throw new AdnInvalidResponseException;
        }

        $nsu = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        if ($nsu === false) {
            throw new AdnInvalidResponseException;
        }

        return $nsu;
    }
}
