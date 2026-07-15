<?php

namespace App\Services\Outbound;

use InvalidArgumentException;
use RuntimeException;

/**
 * Config tipada do canal SVRS NF-e 55 (NFESSL). Orçamento no governador compartilhado.
 */
final class SvrsNfe55Config
{
    public function retrievalEnabled(): bool
    {
        return (bool) config('sefaz.svrs_nfe55_xml.retrieval_enabled', false);
    }

    public function autoQueueEnabled(): bool
    {
        return (bool) config('sefaz.svrs_nfe55_xml.auto_queue_enabled', false);
    }

    public function pilotAllowlistOnly(): bool
    {
        return (bool) config('sefaz.svrs_nfe55_xml.pilot_allowlist_only', false);
    }

    public function host(): string
    {
        $host = strtolower(trim((string) config('sefaz.svrs_nfe55_xml.host', 'dfe-portal.svrs.rs.gov.br')));
        $this->assertAllowedHost($host);

        return $host;
    }

    /**
     * @return list<string>
     */
    public function allowedHosts(): array
    {
        $hosts = config('sefaz.svrs_nfe55_xml.allowed_hosts', ['dfe-portal.svrs.rs.gov.br']);
        if (! is_array($hosts) || $hosts === []) {
            throw new RuntimeException('Lista de hosts SVRS NF-e 55 vazia.');
        }

        return array_values(array_map(
            static fn ($h) => strtolower(trim((string) $h)),
            $hosts
        ));
    }

    public function getUrl(): string
    {
        return $this->buildUrl($this->getPath());
    }

    public function postUrl(): string
    {
        return $this->buildUrl($this->postPath());
    }

    public function getPath(): string
    {
        return $this->normalizePath((string) config('sefaz.svrs_nfe55_xml.get_path', '/NFESSL/DownloadXMLDFe'));
    }

    public function postPath(): string
    {
        return $this->normalizePath((string) config('sefaz.svrs_nfe55_xml.post_path', '/NfeSSL/DownloadXmlDfe'));
    }

    public function timeoutSeconds(): int
    {
        return max(1, (int) config('sefaz.svrs_nfe55_xml.timeout_seconds', 30));
    }

    public function connectTimeoutSeconds(): int
    {
        return max(1, (int) config('sefaz.svrs_nfe55_xml.connect_timeout_seconds', 10));
    }

    public function maxHtmlBytes(): int
    {
        return max(64, (int) config('sefaz.svrs_nfe55_xml.max_html_bytes', 524288));
    }

    public function maxLiteralBytes(): int
    {
        return max(64, (int) config('sefaz.svrs_nfe55_xml.max_literal_bytes', 262144));
    }

    public function maxXmlBytes(): int
    {
        return max(64, (int) config('sefaz.svrs_nfe55_xml.max_xml_bytes', 262144));
    }

    public function queue(): string
    {
        return (string) config('sefaz.svrs_nfe55_xml.queue', 'capture-outbound-ma');
    }

    public function lockTtlSeconds(): int
    {
        return max(30, (int) config('sefaz.svrs_nfe55_xml.lock_ttl_seconds', 180));
    }

    public function parserVersion(): string
    {
        return (string) config('sefaz.svrs_nfe55_xml.wrapper_parser_version', '1');
    }

    /**
     * @return array{sistema: string, OrigemSite: string}
     */
    public function postStaticFields(): array
    {
        $fields = config('sefaz.svrs_nfe55_xml.post_fields', [
            'sistema' => 'Nfe',
            'OrigemSite' => '0',
        ]);

        return [
            'sistema' => (string) ($fields['sistema'] ?? 'Nfe'),
            'OrigemSite' => (string) ($fields['OrigemSite'] ?? '0'),
        ];
    }

    public function isHostAllowed(string $host): bool
    {
        return in_array(strtolower(trim($host)), $this->allowedHosts(), true);
    }

    public function assertAllowedHost(string $host): void
    {
        if (! $this->isHostAllowed($host)) {
            throw new InvalidArgumentException('Host SVRS NF-e 55 não allowlisted.');
        }
    }

    public function assertUrlAllowed(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            throw new InvalidArgumentException('URL SVRS inválida.');
        }
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException('Somente HTTPS é permitido.');
        }
        $this->assertAllowedHost((string) ($parts['host'] ?? ''));
    }

    private function buildUrl(string $path): string
    {
        $url = 'https://'.$this->host().$path;
        $this->assertUrlAllowed($url);

        return $url;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('Path SVRS deve começar com /.');
        }
        if (str_contains($path, '://') || str_contains($path, '?') || str_contains($path, '#')) {
            throw new InvalidArgumentException('Path SVRS inválido.');
        }

        return $path;
    }
}
