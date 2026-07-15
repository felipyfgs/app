<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Teste arquitetural: clientes HTTP SVRS não devem contornar o governador.
 * Heurística estática — falha se novos curl_init aparecerem fora dos clients allowlisted.
 */
class SvrsPortalClientsUseGovernorTest extends TestCase
{
    public function test_apenas_clients_allowlisted_tocam_host_svrs(): void
    {
        $root = dirname(__DIR__, 2).'/app';
        $allowed = [
            'Services/Outbound/HttpSvrsNfceOutboundXmlRetrievalClient.php',
            'Services/Outbound/HttpSvrsNfe55OutboundXmlRetrievalClient.php',
        ];
        $hits = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $rel = substr($path, strlen($root) + 1);
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }
            if (! str_contains($content, 'dfe-portal.svrs.rs.gov.br')
                && ! str_contains($content, 'DownloadXMLDFe')
                && ! str_contains($content, 'NFESSL')
                && ! str_contains($content, 'NFCESSL')) {
                continue;
            }
            // Config/parser/docs em strings ok; curl_init é o cheiro de transporte
            if (! str_contains($content, 'curl_init')) {
                continue;
            }
            $ok = false;
            foreach ($allowed as $a) {
                if (str_ends_with($rel, $a) || $rel === $a) {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                $hits[] = $rel;
            }
        }

        $this->assertSame([], $hits, 'Clientes SVRS com curl_init fora da allowlist: '.implode(', ', $hits));
    }
}
